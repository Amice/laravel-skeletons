<?php

namespace KovacsLaci\LaravelSkeletons\Services\Views;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateViewGenerator extends BaseViewGenerator
{

    public function generate(): ?array
    {
        $stubFileName = 'create.stub';
        $stubFilePath = $this->withBootStrap ? "views/bootstrap/$stubFileName" : "views/$stubFileName";
        try {
            $stubContent = self::getStubContent($stubFilePath);
        }
        catch (\Exception $e) {
            $this->command->error($e->getMessage());
            return null;
        }

        $fields = '';
        foreach ($this->columns as $column) {
            $required = $column['is_nullable'] ? '' : 'required';
            if (!empty($column['is_foreign'])) {
                $relatedTable = $column['related_table']; // e.g., 'school_classes'
                $entities = Str::camel($relatedTable);
                $entity = Str::singular($entities);
                // Generate a <select> dropdown for foreign key fields
                $field = <<<HTML
                    <fieldset>
                        <label for="{$column['name']}">
                            {{ __('{$this->tableName}.{$column['name']}') }}
                        </label>
                        <select name="{$column['name']}" id="{$column['name']}" $required>
                            <option value="">{{ __('skeletons.select') }}</option>
                            @foreach(\${$entities} as \${$entity})
                                <option value="{{ \${$entity}->id }}">{{ \${$entity}->name }}</option>
                            @endforeach
                        </select>
                    </fieldset>
                HTML;
            } else {
                $field = <<<HTML
                    <fieldset>
                        <label for="{$column['name']}">
                            {{ __('{$this->tableName}.{$column['name']}') }}
                        </label>
                        <input
                            type="{$this->getInputType($column['type'])}"
                            name="{$column['name']}"
                            id="{$column['name']}"
                            $required
                            placeholder="{{ __('{$this->tableName}.{$column['name']}') }}"
                            value="{{ old('{$column['name']}') }}"
                        >
                    </fieldset>
                HTML;
            }
            $fields .= $field . "\n";
        }
        $placeHolders = [
            '{{ form_fields }}' => $fields,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeHolders);
        $viewPath = $this->getPath(resource_path("views/{$this->tableName}"));
        File::ensureDirectoryExists($viewPath);
        $filePath = $this->getPath("{$viewPath}/create.blade.php");
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;
        $this->command->info("✅ View created: {$filePath}");

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }
}
