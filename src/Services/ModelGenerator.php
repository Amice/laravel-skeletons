<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelGenerator extends AbstractGenerator
{
    public function generate(): ?array
    {
        // Try to get the stub content.
        try {
            $stubContent = self::getStubContent('model.stub');
        } catch (\Exception $e) {
            $this->command->error($e->getMessage());
            return null;
        }

        // Prepare replacements for placeholders.
        $fillable = "'" . implode("', '", array_column($this->columns, 'name')) . "'";
        $relationshipsCode = $this->generateRelationshipsCode($this->relationships);
        $primaryKey = isset($this->primaryKey) && $this->primaryKey !== 'id'
            ? "\nprotected \$primaryKey = '{$this->primaryKey}';\n"
            : '';
        $keyType = isset($this->keyType) && $this->keyType !== 'int'
            ? "\nprotected \$keyType = '{$this->keyType}';\n"
            : '';
        $noTimestamps = empty($parsedData['hasTimestamps'])
            ? "public \$timestamps = false;\n"
            : '';
        // Define the placeholders and their replacements.
        $placeholders = [
            '{{ className }}'       => $this->modelName,
            '{{ fillable }}'        => $fillable,
            '{{ relationships }}'   => $relationshipsCode,
            '{{ primaryKey }}'      => $primaryKey,
            '{{ keyType }}'         => $keyType,
            '{{ noTimestamps }}'    => $noTimestamps,
        ];

        // Replace placeholders in the stub.
        $content = $this->replacePlaceholders($stubContent, $placeholders);

        // Define file path and create a backup if needed.
        $filePath = $this->getPath(app_path("Models/{$this->modelName}.php"));
        $this->createBackup($filePath);

        // Write out the new model file.
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;
        $this->command->info("✅ Model created: {$filePath}");

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }

    /**
     * Update the related models by adding a hasMany relationship.
     * Note that in a staged pipeline, all models are generated first.
     * At this stage we only update the files.
     */
    public function updateRelatedModels(): void
    {
        foreach ($this->relationships as $relationship) {
            // Determine the name and path of the related model.
            $relatedModelName = $this->getModelName($relationship['on']);
            $relatedModelPath = $this->getPath(app_path("Models/{$relatedModelName}.php"));

            // During the relationship update phase,
            // we assume that each related model was generated in the model generation phase.
            if (File::exists($relatedModelPath)) {
                $relatedModelContent = File::get($relatedModelPath);
                // Determine the name for the hasMany method in the related model.
                $hasManyMethodName = Str::camel(Str::plural($this->modelName));

                // Only add the method if it does not already exist.
                if (!Str::contains($relatedModelContent, "function {$hasManyMethodName}()")) {
                    $hasManyRelationshipCode = <<<PHP
                        public function {$hasManyMethodName}()
                        {
                            return \$this->hasMany({$this->modelName}::class, '{$relationship['column']}', 'id');
                        }

                    PHP;
                    $relatedModelContent = $this->insertIntoClassBody($relatedModelContent, $hasManyRelationshipCode);
                    File::put($relatedModelPath, $relatedModelContent);
                    $this->command->info("Added hasMany method to related model: {$relatedModelPath}");
                }
            } else {
                // Log a warning if the related model file isn’t found.
                $this->command->warn("❗Related model file not found: {$relatedModelPath}");
            }
        }
    }

    /**
     * Generates code for belongsTo relationships for the current model.
     */
    protected function generateRelationshipsCode(array $relationships): string
    {
        $code = '';
        foreach ($relationships as $relation) {
            $relatedModel = self::getModelName($relation['on']);
            $methodName = Str::camel(Str::singular($relation['on']));

            $code .= <<<PHP

                public function {$methodName}()
                {
                    return \$this->belongsTo({$relatedModel}::class, '{$relation['column']}', 'id');
                }

            PHP;
        }
        return $code;
    }

    /**
     * Inserts code into the class body just before the closing brace.
     */
    protected function insertIntoClassBody(string $modelContent, string $relationshipCode): string
    {
        $position = strrpos($modelContent, '}');
        if ($position === false) {
            throw new \RuntimeException("Invalid model file: Unable to find closing brace for class.");
        }
        return substr($modelContent, 0, $position) . "\n" . $relationshipCode . "\n" . substr($modelContent, $position);
    }

}
