<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Class RequestGenerator
 *
 * Generates the Request class file for a given model based on a stub file.
 * It replaces placeholders with dynamic validation rules, patch rules, and messages,
 * writes the file to the Http/Requests directory, and updates the validation language files.
 *
 * @package App\Services
 */
class RequestGenerator extends AbstractGenerator
{
    /**
     * Generates the Request class file.
     *
     * Reads the 'request.stub' file, replaces its placeholders with generated
     * class name, validation rules, patch rules, and validation messages, writes
     * the final content to a new Request class file, and then updates the language
     * files for validation messages.
     *
     * @return void
     * @throws \Exception If the stub file is missing or the language file doesn't return an array.
     */
    public function generate(): ?array
    {
        $stubFileName = self::stub_path('request.stub');
        try {
            $stubContent = self::getStubContent($stubFileName);
        } catch (\Exception $e) {
            $this->command->error($e->getMessage());
            return null;
        }

        // Generate placeholders.
        $className = $this->modelName . "Request";
        $rules = $this->generateValidationRules();
        $patchRules = $this->generateValidationPatchRules();
        $messagesForRequest = $this->generateValidationMessagesForRequest();
        $indent = Str::repeat(" ", 12);
        $strMessages = implode(",\n$indent", $messagesForRequest) . ",";

        // Replace placeholders in the stub.
        $placeholders = [
            '{{ className }}' => $className,
            '{{ rules }}' => $rules,
            '{{ patchRules }}' => $patchRules,
            '{{ messages }}' => $strMessages,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeholders);
        $filePath = self::getPath(app_path("Http/Requests/{$className}.php"));
        File::ensureDirectoryExists(File::dirname($filePath), true);
        $this->createBackup($filePath);
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;

        $this->command->info("✅ Request created: {$filePath}");

        if (!$this->isApi) {
            // Update the language file for validation messages.
            $this->updateValidationLanguageFiles();
        }

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }

    /**
     * Generates a string containing the validation rules for each column.
     *
     * Iterates over each column from the migration data. For each column, if the column is not nullable,
     * it marks it as 'required', then adds a type-based rule (for example, 'integer', 'string', or 'date').
     * Each column's rules are formatted as "'column_name' => 'rule1|rule2|...'" and concatenated into a single string.
     *
     * @return string The generated validation rules string.
     */
    protected function generateValidationRules(): string
    {
        $rules = [];
        foreach ($this->columns as $column) {
            $rule = [];
            if (!$column['is_nullable']) {
                $rule[] = 'required';
            }

            // Add type-based rules.
            switch ($column['type']) {
                case 'integer':
                    $rule[] = 'integer';
                    break;
                case 'string':
                    $rule[] = 'string';
                    break;
                case 'date':
                    $rule[] = 'date';
                    break;
            }

            $rules[] = "'{$column['name']}' => '" . implode('|', $rule) . "'";
        }
        $indent = Str::repeat(" ", 12);
        return implode(",\n$indent", $rules) . ",";
    }

    /**
     * Generates a string containing the validation patch rules.
     *
     * For HTTP PATCH requests, each field is made 'nullable'. Each rule is formatted as
     * "'column_name' => 'nullable'" and concatenated into one string.
     *
     * @return string The generated patch rules string.
     */
    protected function generateValidationPatchRules(): string
    {
        $rules = [];
        foreach ($this->columns as $column) {
            $rules[] = "'{$column['name']}' => 'nullable'";
        }
        $indent = Str::repeat(" ", 16);
        return implode(",\n$indent", $rules) . ",";
    }

    /**
     * Generates an array of validation messages for the Request file.
     *
     * Each column is processed to create a translation string for the 'required' rule.
     * Format: "'column_name.required' => __('validate_model.column_name.required')"
     *
     * @return array An array of validation messages for use in the Request class.
     */
    protected function generateValidationMessagesForRequest(): array
    {
        $messages = [];
        $model = Str::snake($this->modelName);
        foreach ($this->columns as $column) {
            $messages[] = "'{$column['name']}.required' => __('validate_{$model}.{$column['name']}.required')";
        }
        return $messages;
    }

    /**
     * Generates an associative array of validation messages for the language file.
     *
     * The returned array has keys as validation rule identifiers and values as the message.
     * Format: "column_name.required" => "column_name is required"
     *
     * @param array $columns The columns extracted from migration data.
     * @return array An associative array of validation messages.
     */
    protected function generateValidationMessagesLang(array $columns): array
    {
        $messages = [];
        foreach ($columns as $column) {
            $messages["{$column['name']}.required"] = "{$column['name']} is required";
        }

        return $messages;
    }

    /**
     * Updates (or creates) validation language files for all locales.
     *
     * Scans the resources/lang directory for locale folders, and for each locale, it ensures
     * a language file for the current model's validation rules exists (or creates a new one).
     * Then, it merges the new validation messages with any existing ones and writes the updated
     * array back to the file.
     *
     * @return void
     * @throws \Exception If the existing language file does not return an array.
     */
    protected function updateValidationLanguageFiles(): void
    {
        $langDir = self::getPath(resource_path('lang'));
        $locales = array_filter(scandir($langDir), function ($dir) use ($langDir) {
            return is_dir($langDir . DIRECTORY_SEPARATOR . $dir) && !in_array($dir, ['.', '..']);
        });

        $model = Str::snake($this->modelName);
        foreach ($locales as $locale) {
            $filePath = $langDir . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . "validate_{$model}.php";

            // Ensure the file exists or create a new one.
            if (!File::exists($filePath)) {
                File::put($filePath, "<?php\n\nreturn [\n\n];");
                $this->generatedFiles[] = $filePath;
                $this->command->info("✅ Created validation file for locale: {$locale}");
            }

            // Load existing messages.
            $existingMessages = include $filePath;
            if (!is_array($existingMessages)) {
                throw new \Exception("Validation file for locale {$locale} does not return an array.");
            }

            // Merge new messages.
            $newMessages = $this->generateValidationMessagesLang($this->columns);
            $updatedMessages = array_merge($existingMessages, $newMessages);

            // Write updated messages back to the file.
            $content = "<?php\n\nreturn " . var_export($updatedMessages, true) . ";\n";
            File::put($filePath, $content);
            $this->command->info("Updated validation file for locale: {$locale}");
        }
    }
}
