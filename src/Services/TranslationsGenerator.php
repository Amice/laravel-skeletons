<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TranslationsGenerator extends AbstractGenerator
{

    public function generate(): ?array
    {
        // Build a flat language array:
        $langArray = [];
        // Use a lowercase key for the singular model (e.g. "subject" => "Subject")
        $langArray[Str::lower(Str::singular($this->tableName))] = $this->modelName;
        // Use the table name as key for the plural (e.g. "subjects" => "Subjects")
        $langArray[$this->tableName] = ucfirst(Str::replace('_', ' ', $this->tableName));

        // Add each fillable field. If a fillable is an array (with a "name" key) pick that,
        // otherwise use the value directly.
        foreach ($this->columns as $field) {
            $fieldName = is_array($field) ? $field['name'] : $field;
            $langArray[$fieldName] = ucfirst(Str::replace('_', ' ', $fieldName));
        }

        // Path to the language directory
        $langPath = resource_path('lang');
        // Get all directories (languages) inside resources/lang, e.g. "en", "es", etc.
        $languageDirs = File::directories($langPath);

        foreach ($languageDirs as $langDir) {
            $filePath = $langDir . DIRECTORY_SEPARATOR . "{$this->tableName}.php";

            $this->createBackup($filePath);
            // Create PHP file content that returns the language array
            $content = "<?php\n\nreturn " . var_export($langArray, true) . ";\n";
            File::put($filePath, $content);
            $this->generatedFiles[] = $filePath;
            $this->command->info("✅ Language file created: {$filePath}");
        }

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }

}
