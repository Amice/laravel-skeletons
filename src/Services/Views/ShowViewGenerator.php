<?php

namespace App\Services\Views;

use Illuminate\Support\Facades\File;

class ShowViewGenerator extends BaseViewGenerator
{

    public function generate(): ?array
    {
        $stubFileName = 'show.stub';
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
            $field = <<<HTML
                <li>
                    <strong>{{ __('{{table_name}}.{$column["name"]}') }}:</strong>
                    {{ \${{singular}}->{$column['name']} }}
                </li>
            HTML;
            $fields .= $field;

        }
        $placeHolders = [
            '{{form_fields}}' => $fields,
            '{{table_name}}' => $this->tableName,
            '{{singular}}' => $this->singular,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeHolders);
        $viewPath = $this->getPath(resource_path("views/{$this->tableName}"));
        File::ensureDirectoryExists($viewPath);
        $filePath = $this->getPath("{$viewPath}/show.blade.php");
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;
        $this->command->info("View created: {$filePath}");

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }
}
