<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ViewUpdater
{
    private string $model;
    private object $modelInstance;
    private string $tableName;
    private string $primaryKey;
    private array $columns;
    private array $fillableFields;
    private $command; // Stores reference to Artisan command

    public function __construct(string $model, $command)
    {
        $this->command = $command; // Store the command instance
        $this->model = $model;
        $this->initializeModel();
    }

    private function initializeModel(): void
    {
        $modelClass = "App\\Models\\{$this->model}";
        if (!class_exists($modelClass)) {
            throw new \Exception("Model does not exist: $modelClass");
        }

        $this->modelInstance = new $modelClass();
        $this->tableName = $this->modelInstance->getTable();
        $this->primaryKey = $this->modelInstance->getKeyName();
        $this->columns = DB::select("SHOW COLUMNS FROM `{$this->tableName}`");
        $this->fillableFields = !empty($this->modelInstance->getFillable())
            ? $this->modelInstance->getFillable()
            : array_map(fn($column) => $column->Field, $this->columns);
    }

    public function updateViews(): void
    {
        $viewTypes = ['index', 'create', 'edit', 'show'];

        foreach ($viewTypes as $viewType) {
            $viewPath = resource_path("views/{$this->tableName}/{$viewType}.blade.php");

            if (!File::exists($viewPath)) {
                continue; // Skip if view doesn't exist
            }

            $content = $this->generateViewContent($viewType);
            File::ensureDirectoryExists(resource_path("views/{$this->tableName}"));
            File::put($viewPath, $content);
        }
    }

    private function generateViewContent(string $viewType): string
    {
        $singular = Str::lower($this->model);
        $viewContent = File::get(resource_path("views/{$this->tableName}/{$viewType}.blade.php"));

        if ($viewType === 'index') {
            $viewContent = str_replace('<!-- table-head -->', $this->generateTableHead(), $viewContent);
            $viewContent = str_replace('<!-- table-columns -->', $this->generateTableFields($singular), $viewContent);
            return $viewContent;
        } elseif ($viewType === 'create') {
            return str_replace('<!-- form-input-fields -->', $this->generateFormFields($singular), $viewContent);
        } elseif ($viewType === 'edit') {
            return str_replace('<!-- form-input-fields -->', $this->generateEditFormFields($singular), $viewContent);
        } elseif ($viewType === 'show') {
            return str_replace('<!-- display-data -->', $this->generateShowFields($singular), $viewContent);
        }

        return $viewContent;
    }

    private function generateTableFields(string $singular): string
    {
        return implode("\n", array_map(fn($field) => "<td>{{ \${$singular}->$field }}</td>", $this->fillableFields));
    }

    private function generateTableHead(): string
    {
        return implode("\n", array_map(fn($field) => "<th>{{ __('messages.$field') }}</th>", $this->fillableFields));
    }

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

    private function getColumnType(string $field): string
    {
        $mapping = [
            'varchar' => 'text', 'char' => 'text', 'text' => 'textarea',
            'tinytext' => 'textarea', 'mediumtext' => 'textarea', 'longtext' => 'textarea',
            'int' => 'number', 'tinyint' => 'checkbox', 'smallint' => 'number',
            'mediumint' => 'number', 'bigint' => 'number', 'decimal' => 'number',
            'float' => 'number', 'double' => 'number', 'date' => 'date',
            'datetime' => 'datetime-local', 'timestamp' => 'datetime-local', 'time' => 'time',
            'year' => 'number', 'boolean' => 'checkbox', 'json' => 'text',
            'enum' => 'select'
        ];

        $columnType = Str::lower($field);
        $baseType = strtok($columnType, '(');

        return $mapping[$baseType] ?? 'text';
    }

    private function extractEnumOptions(string $field): array
    {
        // Extract ENUM values
        $optionsString = substr($field, strpos($field, '(') + 1, -1);
        return array_map('trim', explode(',', str_replace("'", '', $optionsString)));
    }

    private function getRowActions()
    {
        $singular = Str::lower($this->model);
        $plural = $this->tableName;
        return <<<BLADE
        <td>
            <a href="{{ route('$plural.show', $$singular->{$this->primaryKey}) }}">{{ __('skeletons.show')}}</a>
            {{-- uncomment @if / @endif when authentication is required --}}
            {{-- @if(auth()->check()) --}}
                <a href="{{ route('$plural.edit', $$singular->{$this->primaryKey}) }}">{{ __('skeletons.edit') }}</a>
                <form action="{{ route('$plural.destroy', $$singular->{$this->primaryKey}) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit">{{ __('skeletons.delete') }}</button>
                </form>
            {{-- @endif --}}
        </td>
        BLADE;
    }
}
