<?php

namespace App\Services\Views;

use App\Services\AbstractGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
            $rowItem = "{{ \${$this->singular}->{$column['name']} }}";
            if (!empty($column['is_foreign'])) {
                // If it's a foreign key, output the related model’s "name" property.
                // We assume that the relationship method is defined on the model
                // and that the related model has a 'name' column.
                $rowItem = "<td>{{ \${$this->singular}->" . Str::camel(Str::singular($column['related_table'])) . "->name ?? '' }}</td>";
            }
            $field = <<<HTML
                <li>
                    <strong>{{ __('{$this->tableName}.{$column["name"]}') }}:</strong>
                    $rowItem
                </li>
            HTML;
            $fields .= $field;

        }
        $placeHolders = [
            '{{ form_fields }}' => $fields,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeHolders);
        $viewPath = $this->getPath(resource_path("views/{$this->tableName}"));
        File::ensureDirectoryExists($viewPath);
        $filePath = $this->getPath("{$viewPath}/show.blade.php");
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;
        $this->command->info("✅ View created: {$filePath}");

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }
}
