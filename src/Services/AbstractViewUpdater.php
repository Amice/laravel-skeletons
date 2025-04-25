<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class AbstractViewUpdater
{
//    protected static $viewTypes = ['index', 'create', 'show', 'edit'];
//    protected string $model;
//    protected object $modelInstance;
//    protected string $tableName;
//    protected string $primaryKey;
//    protected string $viewType;
//    protected string $viewPath;
//    protected string $viewContent;
//    protected array $columns;
//    protected array $fillableFields;
//    protected $command; // Stores reference to Artisan command
//
//    public function __construct(string $model, $command)
//    {
//        $this->command = $command; // Store the command instance
//        $this->model = $model;
//
//        if (!isset($this->viewType) || empty($this->viewType)) {
//            throw new \Exception("The viewType property must be defined in the child class.");
//        }
//
//        $modelClass = "App\\Models\\{$this->model}";
//        if (!class_exists($modelClass)) {
//            throw new \Exception("Model does not exist: $modelClass");
//        }
//
//        $this->modelInstance = new $modelClass();
//        $this->tableName = $this->modelInstance->getTable();
//        $this->primaryKey = $this->modelInstance->getKeyName();
//
//        $this->columns = DB::select("SHOW COLUMNS FROM `{$this->tableName}`");
//        if (empty($this->columns)) {
//            throw new \Exception("No columns found for table: {$this->tableName}. Please run the migration first: <fg=yellow>php artisan migrate</>");
//        }
//
//        $this->fillableFields = !empty($this->modelInstance->getFillable())
//            ? $this->modelInstance->getFillable()
//            : array_map(fn($column) => $column->Field, $this->columns);
//
//        // Define the view path
//        $this->viewPath = resource_path("views" . DIRECTORY_SEPARATOR . $this->tableName . DIRECTORY_SEPARATOR . $this->viewType . ".blade.php");
//
//
//        // Check if the view file exists
//        if (!File::exists($this->viewPath)) {
//            $this->command->info("View does not exist: {$this->viewPath}");
//            return;
//        }
//
//        $this->viewContent = File::get($this->viewPath);
//    }

    use ModelDataTrait;

    protected $command; // Stores Artisan command reference
    protected string $viewType;
    protected string $viewPath;
    protected string $viewContent;

    public function __construct(string $model, $command)
    {
        $this->command = $command;
        $this->initializeModelData($model);

        // Define the view path
        $this->viewPath = resource_path("views" . DIRECTORY_SEPARATOR . $this->tableName . DIRECTORY_SEPARATOR . $this->viewType . ".blade.php");

        // Check if view file exists
        if (!File::exists($this->viewPath)) {
            $this->command->info("View does not exist: {$this->viewPath}");
            return;
        }

        $this->viewContent = File::get($this->viewPath);
    }

    abstract public function updateViewContent();
    abstract protected function generateViewContent(): array | string;


    public function saveView(): void
    {
//        $this->viewContent = $this->generateViewContent();
        File::ensureDirectoryExists(resource_path("views/{$this->tableName}"));
        File::put($this->viewPath, $this->viewContent);

        $this->command->info(ucfirst($this->viewType) . " view updated successfully: $this->viewPath");
    }
//
//    private function generateFormFields(string $singular): string
//    {
//        return implode("\n", array_map(fn($field) => $this->createField($field), $this->fillableFields));
//    }
//
//    private function generateEditFormFields(string $singular): string
//    {
//        return implode("\n", array_map(fn($field) => $this->createField($field, $singular), $this->fillableFields));
//    }
//
//    private function generateShowFields(string $singular): string
//    {
//        return implode("\n", array_map(fn($field) => "<p><strong>{{ __('messages.$field') }}:</strong> {{ \${$singular}->$field }}</p>", $this->fillableFields));
//    }


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

    public function generateTranslationFiles(): void
    {
        // Get the list of all languages based on folder names in resources/lang
        $langDirectories = File::directories(resource_path('lang'));

        foreach ($langDirectories as $langDir) {
            // Extract the language code from the directory path
            $languageCode = basename($langDir);

            // Define the translation file path for the specific language
            $langFile = $langDir . DIRECTORY_SEPARATOR . Str::snake($this->model) . ".php";

            // Build the translation array
            $translations = [];
            foreach ($this->fillableFields as $field) {
                // Generate generic placeholder translations
                $translations[$field] = ucfirst(str_replace('_', ' ', $field));
            }

            // Convert the array to a PHP file
            $translationContent = "<?php\n\nreturn " . var_export($translations, true) . ";\n";

            // Save the file
            File::put($langFile, $translationContent);

            // Notify the developer
            $this->command->info("Translation file created for language [{$languageCode}]: {$langFile}");
        }
    }

}
