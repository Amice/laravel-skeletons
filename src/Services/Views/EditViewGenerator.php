<?php

namespace App\Services\Views;


use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EditViewGenerator extends BaseViewGenerator
{

    public function generate(): ?array
    {
        $stubFileName = 'edit.stub';
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
                $relatedTable = $column['related_table'];
                $entities = Str::camel($relatedTable);
                $entity = Str::singular($entities);
                // Generate a <select> dropdown for foreign key fields
                $field = <<<HTML
                    <fieldset>
                        <label for="{$column['name']}">
                            {{ __('{{table_name}}.{$column['name']}') }}
                        </label>
                        <select name="{$column['name']}" id="{$column['name']}" $required>
                            <option value="">{{ __('skeletons.select') }}</option>
                            @foreach(\${$entities} as \${$entity})
                                {{ \$selected = '' }}
                                @if(\${$entity}->id == \${$this->singular}->{$column['name']})
                                    {{ \$selected = 'selected' }}
                                @endif
                                <option value="{{ \${$entity}->id }}" {{ \$selected }}>{{ \${$entity}->name }}</option>
                            @endforeach
                        </select>
                    </fieldset>
                HTML;
            } else {
                $field = <<<HTML
                    <fieldset>
                        <label for="{$column['name']}">
                            {{ __('{{table_name}}.{$column['name']}') }}
                        </label>
                        <input
                            type="{$this->getInputType($column['type'])}"
                            name="{$column['name']}"
                            id="{$column['name']}"
                            $required
                            placeholder="{{ __('{{table_name}}.{$column['name']}') }}"
                            value="{{ old('{$column['name']}', \${{singular}}->{$column['name']}) }}"
                        >
                    </fieldset>
                HTML;
            }
            $fields .= $field . "\n";
        }
        $placeHolders = [
            '{{form_fields}}' => $fields,
            '{{model}}' => $this->modelName,
            '{{table_name}}' => $this->tableName,
            '{{singular}}' => $this->singular,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeHolders);
        $viewPath = $this->getPath(resource_path("views/{$this->tableName}"));
        File::ensureDirectoryExists($viewPath);
        $filePath = $this->getPath("{$viewPath}/edit.blade.php");
//        $this->writeFile($filePath, $content);
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;
        $this->command->info("View created: {$filePath}");

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }
}
