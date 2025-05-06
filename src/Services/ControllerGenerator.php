<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * ControllerGenerator
 *
 * Generates a Laravel controller file based on a provided stub and migration data.
 *
 * This class processes a stub file, replacing placeholders for class names,
 * related models, and other necessary code fragments. It also creates backups of
 * existing controller files before overwriting them.
 *
 * @package App\Services
 */
class ControllerGenerator extends AbstractGenerator
{

    /**
     * Generates the controller file for the model.
     *
     * Reads a stub file called 'controller.stub', replaces the placeholders with
     * actual values such as the class name, use statements, and related data. Also
     * generates code to fetch related models' data and builds the appropriate
     * compact() string for controller methods.
     *
     * If the stub file cannot be loaded, the process will log an error message
     * and return without generating the file.
     *
     * @return null
     * @throws \Exception If any issues occur that require halting the process.
     */
    public function generate(): ?array
    {
        $stubFileName = 'controller.stub';
        try {
            $stubContent = self::getStubContent($stubFileName);
        }
        catch (\Exception $e) {
            $this->command->error($e->getMessage());
            return null;
        }

        $className = $this->modelName . 'Controller';

        // Detect related models for use statements and data fetching
        $useStatements = [];
        $useStatements[] = "use App\Models\\{$this->modelName};";
        $useStatements[] = "use App\Http\Requests\\{$this->modelName}Request;";
        $relatedData = ''; // Stores data fetching code
        $relatedCompactCreate = []; // Array to store compact variables for create method
        $relatedCompactEdit = "'{$this->singular}'"; // Always include main model in edit method
        $relatedModels = [];
        foreach ($this->columns as $column) {
            if (!empty($column['is_foreign'])) {
                // Convert related table name into model and variable names
                $relatedModel = self::getModelName($column['related_table']);
                $relatedVariable = Str::camel(Str::plural($column['related_table'])); // Variable name (e.g., schoolClasses)

                $useStatements[] = "use App\Models\\{$relatedModel};";
                $relatedData .= "\n        \$$relatedVariable = $relatedModel::all();";

                // Add related variables to array for compact
                $relatedCompactCreate[] = $relatedVariable;
                $relatedCompactEdit .= ", '$relatedVariable'";
            }
        }

        // Build compact() string for create method properly
        $relatedCompactCreateString = empty($relatedCompactCreate)
            ? ''
            : ", compact('" . implode("', '", $relatedCompactCreate) . "')";

        $useStatements = array_unique($useStatements);
        $useStatementsString = empty($useStatements)
            ? ''
            : implode("\n", $useStatements);
        // Replace placeholders in the stub file
        $placeholders = [
            '{{ className }}' => $className,
            '{{ useStatements }}' => $useStatementsString,
            '{{ relatedData }}' => $relatedData,
            '{{ relatedCompactCreate }}' => $relatedCompactCreateString,
            '{{ relatedCompactEdit }}' => $relatedCompactEdit,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeholders);
        $filePath = $this->getPath(app_path("Http/Controllers/$className.php"));
        $this->createBackup($filePath);
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;
        $this->command->info("✅ Controller created: {$filePath}");

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }

}
