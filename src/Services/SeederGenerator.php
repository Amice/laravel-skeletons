<?php


namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SeederGenerator extends AbstractGenerator
{
    /**
     * Generate a seeder based on the available data file and row count.
     *
     * @return array|null
     */
    public function generate(): ?array
    {
        // Load the stub content.
        try {
            $stubContent = self::getStubContent("seeder.stub");
        } catch (\Exception $e) {
            $this->command->error($e->getMessage());
            return null;
        }
        // For bulk (chunked) insertion, generate a PHP array representation of column names.
        $columnsArray = "['" . implode("', '", array_column($this->columns, 'name')) . "']";
        // Prepare the base placeholders.
        $placeholders = [
            '{{ columnsArray }}' => $columnsArray,
        ];

        // Replace placeholders in the stub content.
        $content = $this->replacePlaceholders($stubContent, $placeholders);

        // Determine the destination path for the seeder file.
        $destinationPath = self::getPath(database_path("seeders/{$this->modelName}Seeder.php"));

        // Create a backup if the file already exists.
        $this->createBackup($destinationPath);

        // Write the generated content to the destination file.
        File::put($destinationPath, $content);
        $this->generatedFiles[] = $destinationPath;
        $this->command->info("✅ Seeder created: {$destinationPath}");

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files' => $this->backupFiles,
        ];
    }


//namespace App\Services;
//
//    use Illuminate\Support\Facades\File;
//    use Illuminate\Support\Str;
//
//class SeederGenerator extends AbstractGenerator
//{
//    /**
//     * Generate a seeder based on the available data file and row count.
//     *
//     * @return array|null
//     */
//    public function generate(): ?array
//    {
//        $extensions = ['.txt', '.csv'];
//        $seederType = 'empty';
//        $dataFilePath = null;
//        foreach ($extensions as $extension) {
//            $tempPath = self::getPath(database_path("seeders/data/{$this->tableName}{$extension}"));
//            if (!File::exists($tempPath)) {
//                continue;
//            }
//            $dataFilePath = $tempPath;
//            $total = iterator_count(File::lines($dataFilePath));
//            // If file has more than one row (header + data)
//            if ($total > 1) {
//                // Adjust the threshold for a "big" file as needed.
//                if (($total - 1) > 1000) {
//                    $seederType = 'bulk';
//                } else {
//                    $seederType = 'progress';
//                }
//            }
//            break;
//        }
//
//        // Choose the stub file based on the seeder type.
//        switch ($seederType) {
//            case 'bulk':
//                $stubFileName = 'seeder_bulk.stub';
//                break;
//            case 'progress':
//                $stubFileName = 'seeder_progress.stub';
//                break;
//            default:
//                $stubFileName = 'seeder_empty.stub';
//                break;
//        }
//
//        // Load the stub content.
//        try {
//            $stubContent = self::getStubContent("seeders/{$stubFileName}");
//        } catch (\Exception $e) {
//            $this->command->error($e->getMessage());
//            return null;
//        }
//
//        // Prepare the base placeholders.
//        $placeholders = [
//            '{{ model }}' => $this->modelName,
//            '{{ table_name }}' => $this->tableName,
//        ];
//
//        // Depending on the seeder type, generate additional placeholders.
//        if ($seederType === 'bulk') {
//            // For bulk (chunked) insertion, generate a PHP array representation of column names.
//            $columnsArray = "['" . implode("', '", array_column($this->columns, 'name')) . "']";
//            $placeholders['{{columnsArray}}'] = $columnsArray;
//        } elseif ($seederType === 'progress') {
//            // For progressive (row-by-row) seeding, generate mapping lines for the model's create() method.
//            $mappingLines = [];
//            foreach ($this->columns as $i => $col) {
//                $mappingLines[] = self::indent(4) . "'{$col['name']}' => \$row[{$i}],";
//            }
//            $placeholders['{{column_mapping}}'] = implode("\n", $mappingLines);
//        }
//        // For the empty seeder stub, the stub itself includes a warning message.
//
//        // Replace placeholders in the stub content.
//        $content = $this->replacePlaceholders($stubContent, $placeholders);
//
//        // Determine the destination path for the seeder file.
//        $destinationPath = self::getPath(database_path("seeders/{$this->modelName}Seeder.php"));
//
//        // Create a backup if the file already exists.
//        $this->createBackup($destinationPath);
//
//        // Write the generated content to the destination file.
//        File::put($destinationPath, $content);
//        $this->generatedFiles[] = $destinationPath;
//        $this->command->info("✅ Seeder created: {$destinationPath}");
//
//        return [
//            'generated_files' => $this->generatedFiles,
//            'backup_files' => $this->backupFiles,
//        ];
//    }


//    public function updateDatabaseSeeder(): void
//    {
//        // Path to the DatabaseSeeder file.
//        $seederFilePath = self::getPath(database_path('seeders/DatabaseSeeder.php'));
//
//        if (!File::exists($seederFilePath)) {
//            $this->command->error("DatabaseSeeder file not found at {$seederFilePath}");
//            return;
//        }
//
//        $content = File::get($seederFilePath);
//
//        // Our generated seeder class; assuming it's named like ModelNameSeeder.
//        $seederClass = "{$this->modelName}Seeder::class";
//
//        // If the seeder is already added, do nothing.
//        if (strpos($content, $seederClass) !== false) {
//            $this->command->info("{$seederClass} already exists in DatabaseSeeder.");
//            return;
//        }
//
//        // Look for an existing call() block inside the run method.
//        // Note: Adjusted regex formatting for clarity.
//        $pattern = '/(\$this->call\(\s*
//
//\[\s*)(.*?)(\s*\]
//
//\s*\);)/s';
//
//        if (preg_match($pattern, $content, $matches)) {
//            // Capture current calls.
//            $existingCalls = trim($matches[2]);
//
//            // Append our seeder call.
//            $newCalls = $existingCalls !== ''
//                ? $existingCalls . ",\n            " . $seederClass
//                : $seederClass;
//
//            $replacement = $matches[1] . "\n            " . $newCalls . "\n        " . $matches[3];
//            $content = preg_replace($pattern, $replacement, $content, 1);
//        } else {
//            // If there's no call() block, we insert one at the beginning of the run() method.
//            $patternRun = '/(public function run\(\): void\s*\{\s*)/';
//            $replacement = '$1' . "\n        \$this->call([\n            {$seederClass}\n        ]);\n";
//            $content = preg_replace($patternRun, $replacement, $content, 1);
//        }
//
//        File::put($seederFilePath, $content);
//        $this->command->info("DatabaseSeeder updated with {$seederClass}.");
//    }
}



//
//namespace App\Services;
//
//use Illuminate\Support\Facades\File;
//use Illuminate\Support\Str;
//
//class SeederGenerator extends AbstractGenerator
//{
//    /**
//     * Generate a seeder based on the available data file and row count.
//     *
//     * @return array|null
//     */
//    public function generate(): ?array
//    {
//        $extensions = ['.txt', '.csv'];
//        $seederType = 'empty';
//        foreach ($extensions as $extension) {
//            $dataFilePath = self::getPath(database_path("seeders/data/{$this->tableName}" . $extension));
//            if (!File::exists($dataFilePath)) {
//                continue;
//            }
//            $total = iterator_count(File::lines($dataFilePath));
//            // If only the header row exists, use the empty stub.
//            if (($total) > 1000) { // Adjust the threshold for a "big" file as needed.
//                $seederType = 'bulk';
//            } elseif ($total > 1) {
//                $seederType = 'progress';
//            }
//            break;
//        }
//
//        // Choose the stub file based on the seeder type.
//        switch ($seederType) {
//            case 'bulk':
//                $stubFileName = 'seeder_bulk.stub';
//                break;
//            case 'progress':
//                $stubFileName = 'seeder_progress.stub';
//                break;
//            default:
//                $stubFileName = 'seeder_empty.stub';
//                break;
//        }
//
//        // Load the stub content.
//        try {
//            $stubContent = self::getStubContent("seeders/{$stubFileName}");
//        } catch (\Exception $e) {
//            $this->command->error($e->getMessage());
//            return null;
//        }
//
//        // Prepare the base placeholders.
//        $placeholders = [
//            '{{ model }}'      => $this->modelName,
//            '{{ table_name }}' => $this->tableName,
//        ];
//
//        // Depending on the seeder type, generate additional placeholders.
//        if ($seederType === 'bulk') {
//            // For bulk insertion using LOAD DATA, create a comma-separated list of column names.
//            $columns = implode(', ', array_column($this->columns, 'name'));
//            $placeholders['{{columns}}'] = $columns;
//        } elseif ($seederType === 'progress') {
//            // For progressive (row-by-row) seeding, generate mapping lines for the model's create() method.
//            $mappingLines = [];
//            foreach ($this->columns as $i => $col) {
//                $mappingLines[] = self::indent(4) . "'{$col['name']}' => \$row[{$i}],";
//            }
//            $placeholders['{{column_mapping}}'] = implode("\n", $mappingLines);
//        }
//        // For the empty seeder stub, the stub itself includes a warning message,
//        // for example:
//        // $this->command->warn("WARNING: The seeder for '{{ model }}' is incomplete because no data file was found. Please complete it manually.");
//
//        // Replace placeholders in the stub content.
//        $content = $this->replacePlaceholders($stubContent, $placeholders);
//
//        // Determine the destination path for the seeder file.
//        $destinationPath = self::getPath(database_path("seeders/{$this->modelName}Seeder.php"));
//
//        // Create a backup if the file already exists.
//        $this->createBackup($destinationPath);
//
//        // Write the generated content to the destination file.
//        File::put($destinationPath, $content);
//        $this->generatedFiles[] = $destinationPath;
//        $this->command->info("✅ Seeder created: {$destinationPath}");
//
//        return [
//            'generated_files' => $this->generatedFiles,
//            'backup_files'    => $this->backupFiles,
//        ];
//    }
//
//    public function updateDatabaseSeeder(): void
//    {
//        // Path to the DatabaseSeeder file.
//        $seederFilePath = self::getPath(database_path('seeders/DatabaseSeeder.php'));
//
//        if (!File::exists($seederFilePath)) {
//            $this->command->error("DatabaseSeeder file not found at {$seederFilePath}");
//            return;
//        }
//
//        $content = File::get($seederFilePath);
//
//        // Our generated seeder class; assuming it's named like ModelNameSeeder.
//        $seederClass = "{$this->modelName}Seeder::class";
//
//        // If the seeder is already added, do nothing.
//        if (strpos($content, $seederClass) !== false) {
//            $this->command->info("{$seederClass} already exists in DatabaseSeeder.");
//            return;
//        }
//
//        // Look for an existing call() block inside the run method.
//        $pattern = '/(\$this->call\(\s*
//
//\[\s*)(.*?)(\s*\]
//
//\s*\);)/s';
//
//        if (preg_match($pattern, $content, $matches)) {
//            // Capture current calls.
//            $existingCalls = trim($matches[2]);
//
//            // Append our seeder call.
//            $newCalls = $existingCalls !== ''
//                ? $existingCalls . ",\n            " . $seederClass
//                : $seederClass;
//
//            $replacement = $matches[1] . "\n            " . $newCalls . "\n        " . $matches[3];
//            $content = preg_replace($pattern, $replacement, $content, 1);
//        } else {
//            // If there's no call() block, we insert one at the beginning of the run() method.
//            $patternRun = '/(public function run\(\): void\s*\{\s*)/';
//            $replacement = '$1' . "\n        \$this->call([\n            {$seederClass}\n        ]);\n";
//            $content = preg_replace($patternRun, $replacement, $content, 1);
//        }
//
//        File::put($seederFilePath, $content);
//        $this->command->info("DatabaseSeeder updated with {$seederClass}.");
//    }
//
//}
