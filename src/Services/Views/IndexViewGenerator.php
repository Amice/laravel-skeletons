<?php

namespace KovacsLaci\LaravelSkeletons\Services\Views;

use KovacsLaci\LaravelSkeletons\Services\AbstractGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IndexViewGenerator extends BaseViewGenerator
{
    private bool $withAuth = false;

    public function __construct($command, array $parsedData, $cssStyle = '', $withAuth = false)
    {
        parent::__construct($command, $parsedData, $cssStyle);
        $this->withAuth = $withAuth;
    }

    public function generate(): ?array
    {
        $stubFileName = 'index.stub';
        $stubFilePath = $this->getStubFilePath($stubFileName);
        try {
            $stubContent = self::getStubContent($stubFilePath);
        }
        catch (\Exception $e) {
            $this->command->error($e->getMessage());
            return null;
        }

        $headersArray = [];
        foreach ($this->columns as $column) {
            $headerItem = AbstractGenerator::indent(6) . "<th>{{ __('" . $this->tableName . "." . $column["name"] . "') }}</th>";
            if (!empty($column['is_foreign'])) {
                $headerItem = AbstractGenerator::indent(6) . "<th>{{ __('" . $column['related_table'] . "." . Str::snake(Str::singular($column['related_table'])) . "') }}</th>";
            }
            $headersArray[] = $headerItem;
        }
        $headers = implode("\n", $headersArray);

        $rowsArray = [];
        foreach ($this->columns as $column) {
            $rowItem = AbstractGenerator::indent(6) . "<td>{{ \${$this->singular}->{$column['name']} }}</td>";
            if (!empty($column['is_foreign'])) {
                // If it's a foreign key, output the related model’s "name" property.
                // We assume that the relationship method is defined on the model
                // and that the related model has a 'name' column.
                $rowItem = AbstractGenerator::indent(6) . "<td>{{ \${$this->singular}->" . Str::camel(Str::singular($column['related_table'])) . "->name ?? '' }}</td>";
            }
            $rowsArray[] = $rowItem;
        }
        $rows = implode("\n", $rowsArray);
        $ifAuth = $this->withAuth ? "@if(auth()->check())" : '';
        $endif = $this->withAuth ? "@endif" : '';

        $placeHolders = [
            '{{ table_headers }}' => $headers,
            '{{ table_rows }}'    => $rows,
            '{{ if_auth }}'       => $ifAuth,
            '{{ endif }}'         => $endif,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeHolders);
        $viewPath = $this->getPath(resource_path("views/{$this->tableName}"));
        File::ensureDirectoryExists($viewPath);
        $filePath = $this->getPath("{$viewPath}/index.blade.php");
//        $this->writeFile($filePath, $content);
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;
        $this->command->info("✅ View created: {$filePath}");

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }
}
