<?php

namespace App\Services\Views;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IndexViewGenerator extends BaseViewGenerator
{

    public function generate(): ?array
    {
        $stubFileName = 'index.stub';
        $stubFilePath = $this->withBootStrap ? "views/bootstrap/$stubFileName" : "views/$stubFileName";
        try {
            $stubContent = self::getStubContent($stubFilePath);
        }
        catch (\Exception $e) {
            $this->command->error($e->getMessage());
            return null;
        }

        $plural = $this->tableName;
        $singular = $this->singular;

        $headersArray = [];
        foreach ($this->columns as $column) {
            $headerItem = "                        <th>{{ __('" . $plural . "." . $column["name"] . "') }}</th>";
            if (!empty($col['is_foreign'])) {
                $headerItem = "                        <th>{{ __('" . $column['related_table'] . "." . Str::snake(Str::singular($column['related_table'])) . "') }}</th>";
            }
            $headersArray[] = $headerItem;
        }
        $headers = implode("\n", $headersArray);

        $rowsArray = [];
        foreach ($this->columns as $column) {
            $rowItem = "                        <td>{{ \${$singular}->{$column['name']} }}</td>";
            if (!empty($col['is_foreign'])) {
                $rowItem = "                        <td>{{ \${$singular}->" . Str::camel(Str::singular($column['related_table'])) . "->name ?? '' }}</td>";
            }
            $rowsArray[] = $rowItem;
        }
        $rows = implode("\n", $rowsArray);

        $placeHolders = [
            '{{table_headers}}' => $headers,
            '{{table_rows}}'     => $rows,
            '{{model}}' => $this->modelName,
            '{{table_name}}' => $this->tableName,
            '{{plural}}' => $plural,
            '{{singular}}' => $this->singular,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeHolders);
        $viewPath = $this->getPath(resource_path("views/{$this->tableName}"));
        File::ensureDirectoryExists($viewPath);
        $filePath = $this->getPath("{$viewPath}/index.blade.php");
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
