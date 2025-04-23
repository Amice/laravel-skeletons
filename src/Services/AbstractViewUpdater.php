<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class AbstractViewUpdater
{
    protected static $viewTypes = ['index', 'create', 'show', 'edit'];
    protected string $model;
    protected object $modelInstance;
    protected string $tableName;
    protected string $primaryKey;
    protected string $viewType;
    protected string $viewPath;
    protected string $viewContent;
    protected array $columns;
    protected array $fillableFields;
    protected $command; // Stores reference to Artisan command

    public function __construct(string $model, $command)
    {
        $this->command = $command; // Store the command instance
        $this->model = $model;

        if (!isset($this->viewType) || empty($this->viewType)) {
            throw new \Exception("The viewType property must be defined in the child class.");
        }

        $modelClass = "App\\Models\\{$this->model}";
        if (!class_exists($modelClass)) {
            throw new \Exception("Model does not exist: $modelClass");
        }

        $this->modelInstance = new $modelClass();
        $this->tableName = $this->modelInstance->getTable();
        $this->primaryKey = $this->modelInstance->getKeyName();

        $this->columns = DB::select("SHOW COLUMNS FROM `{$this->tableName}`");
        if (empty($this->columns)) {
            throw new \Exception("No columns found for table: {$this->tableName}. Please run the migration first: <fg=yellow>php artisan migrate</>");
        }

        $this->fillableFields = !empty($this->modelInstance->getFillable())
            ? $this->modelInstance->getFillable()
            : array_map(fn($column) => $column->Field, $this->columns);




//        // Check if fillable is defined and not empty
//        $this->fillableFields = $this->modelInstance->getFillable();
//
//        if (!empty($this->fillableFields)) {
//            // Skip database column fetching if fillable fields are defined
//            $this->columns = array_map(fn($field) => (object)['Field' => $field], $this->fillableFields);
//            $this->command->info("Using fillable fields for table: {$this->tableName}");
//        } else {
//            // Fetch columns from the database as fallback
//            $this->columns = DB::select("SHOW COLUMNS FROM `{$this->tableName}`");
//            if (empty($this->columns)) {
//                $this->command->info("No columns found for table: {$this->tableName}.");
//            }
//
//            // Use database columns if fillable is empty
//            $this->fillableFields = array_map(fn($column) => $column->Field, $this->columns);
//        }

        // Define the view path
        $this->viewPath = resource_path("views" . DIRECTORY_SEPARATOR . $this->tableName . DIRECTORY_SEPARATOR . $this->viewType . ".blade.php");


        // Check if the view file exists
        if (!File::exists($this->viewPath)) {
            $this->command->info("View does not exist: {$this->viewPath}");
            return;
        }

        $this->viewContent = File::get($this->viewPath);
    }


    public function saveView(): void
    {
//        $this->viewContent = $this->generateViewContent();
        File::ensureDirectoryExists(resource_path("views/{$this->tableName}"));
        File::put($this->viewPath, $this->viewContent);

        $this->command->info(ucfirst($this->viewType) . " view updated successfully: $this->viewPath");
    }

    abstract public function updateViewContent();

    abstract protected function generateViewContent(): array | string;


//    public function updateViews(): void
//    {
//        $viewTypes = ['index', 'create', 'edit', 'show'];
//
//        foreach ($viewTypes as $viewType) {
//            $viewPath = resource_path("views/{$this->tableName}/{$viewType}.blade.php");
//
//            if (!File::exists($viewPath)) {
//                continue; // Skip if view doesn't exist
//            }
//
//            $content = $this->generateViewContent($viewType);
//            File::ensureDirectoryExists(resource_path("views/{$this->tableName}"));
//            File::put($viewPath, $content);
//        }
//    }

//    private function generateViewContent(string $viewType): string
//    {
//        $singular = Str::lower($this->model);
//        $viewContent = File::get(resource_path("views/{$this->tableName}/{$viewType}.blade.php"));
//
//        if ($viewType === 'index') {
//            $viewContent = str_replace('<!-- table-head -->', $this->generateTableHead(), $viewContent);
//            $viewContent = str_replace('<!-- table-columns -->', $this->generateTableFields($singular), $viewContent);
//            return $viewContent;
//        } elseif ($viewType === 'create') {
//            return str_replace('<!-- form-input-fields -->', $this->generateFormFields($singular), $viewContent);
//        } elseif ($viewType === 'edit') {
//            return str_replace('<!-- form-input-fields -->', $this->generateEditFormFields($singular), $viewContent);
//        } elseif ($viewType === 'show') {
//            return str_replace('<!-- display-data -->', $this->generateShowFields($singular), $viewContent);
//        }
//
//        return $viewContent;
//    }


    private function generateFormFields(string $singular): string
    {
        return implode("\n", array_map(fn($field) => $this->createField($field), $this->fillableFields));
    }

    private function generateEditFormFields(string $singular): string
    {
        return implode("\n", array_map(fn($field) => $this->createField($field, $singular), $this->fillableFields));
    }

    private function generateShowFields(string $singular): string
    {
        return implode("\n", array_map(fn($field) => "<p><strong>{{ __('messages.$field') }}:</strong> {{ \${$singular}->$field }}</p>", $this->fillableFields));
    }

    private function createField(string $field, string $singular = null): string
    {
        $inputType = $this->getColumnType($field);
        $existingValue = $singular ? "{{ \${$singular}->$field ?? old('$field') }}" : "{{ old('$field') }}";

        if ($inputType === 'select') {
            $options = $this->extractEnumOptions($field);
            return "<label for='$field'>{{ __('messages.$field') }}</label>
                    <select name='$field' id='$field'>
                        @foreach($options as \$option)
                            <option value='{{ \$option }}' {{ \$option == $existingValue ? 'selected' : '' }}>{{ \$option }}</option>
                        @endforeach
                    </select>";
        } elseif ($inputType === 'checkbox') {
            return "<label for='$field'>{{ __('messages.$field') }}</label>
                    <input type='checkbox' name='$field' id='$field' value='1' {{ $existingValue ? 'checked' : '' }}>";
        }

        return "<label for='$field'>{{ __('messages.$field') }}</label>
                <input type='$inputType' name='$field' id='$field' value='$existingValue'>";
    }

    /**
     * Converts MySQL column type into a corresponding HTML input type.
     */
    protected function getColumnType(object $column): string
    {
        $mapping = [
            'varchar' => 'text',
            'char' => 'text',
            'text' => 'textarea',
            'tinytext' => 'textarea',
            'mediumtext' => 'textarea',
            'longtext' => 'textarea',
            'int' => 'number',
            'tinyint' => 'checkbox',
            'smallint' => 'number',
            'mediumint' => 'number',
            'bigint' => 'number',
            'decimal' => 'number',
            'float' => 'number',
            'double' => 'number',
            'date' => 'date',
            'datetime' => 'datetime-local',
            'timestamp' => 'datetime-local',
            'time' => 'time',
            'year' => 'number',
            'boolean' => 'checkbox',
            'json' => 'text',
            'enum' => 'select'
        ];
        // Extract base type by removing any size constraints (e.g., "varchar(255)" → "varchar")
        $baseType = strtok(Str::lower($column->Type), '(');

        return $mapping[$baseType] ?? 'text';
    }

    protected function extractEnumOptions(string $field): array
    {
        // Extract ENUM values
        $optionsString = substr($field, strpos($field, '(') + 1, -1);
        return array_map('trim', explode(',', str_replace("'", '', $optionsString)));
    }


}
