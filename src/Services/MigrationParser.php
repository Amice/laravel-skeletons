<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrationParser
{
    public static function processMigrationFile(string $filePath): array
    {
        $parsedData = self::parseMigrationFile($filePath);
        $entities   = [];
        self::collectEntities($parsedData, $entities);

        return $entities;
    }

    public static function parseMigrationFile(string $filePath): array
    {
        $content = File::get($filePath);
        $tableName = self::extractTableName($content);
        $columns = self::extractColumns($content);
        $relationships = self::extractRelationships($content);

        return compact('tableName', 'columns', 'relationships');
    }

    public static function collectEntities(array $parsedData, array &$entities): void
    {
        $tableName = $parsedData['tableName'];
        // Validate that all related migrations exist for the current entity
        self::validateRelatedMigrations($parsedData['relationships']);
        // If this table is not already added, do so.
        if (!isset($entities[$tableName])) {
            $entities[$tableName] = $parsedData;

            // Loop through each relationship to collect related entities.
            foreach ($parsedData['relationships'] as $relationship) {
                $relatedTable = $relationship['on'];
                $migrationFile = self::findMigrationByTableName($relatedTable);
                if ($migrationFile) {
                    $relatedData = self::parseMigrationFile($migrationFile);
                    self::collectEntities($relatedData, $entities);
                }
            }
        }
    }

    protected static function validateRelatedMigrations(array $relationships): void
    {
        $missing = [];
        foreach ($relationships as $relationship) {
            $relatedTable = $relationship['on'];
            $relatedMigration = self::findMigrationByTableName($relatedTable);

            if (!$relatedMigration) {
                $missing[] = $relatedTable;
            }
        }

        if (!empty($missing)) {
            throw new \Exception("Related migrations not found for tables: " . implode(', ', $missing) . ". Halting the process.");
        }
    }

    public static function findMigrationByTableName(string $tableName): ?string
    {
        $pattern = AbstractGenerator::getPath(database_path("migrations/*_create_{$tableName}_table.php"));
        $files = glob($pattern);

        return $files[0] ?? null;
    }
    public static function extractTableName(string $content): string
    {
        // Collapse the content into a single line for reliable processing
        $normalizedContent = Str::of($content)->replaceMatches('/\s+/', ' ')->trim();

        // Look for Schema::create and extract table name
        if (Str::contains($normalizedContent, 'Schema::create')) {
            // Extract everything starting after Schema::create(
            $start = Str::after($normalizedContent, 'Schema::create(');

            // Get the table name by stopping at the first comma
            $tableNameWithExtras = Str::before($start, ',');

            // Clean up quotes and spaces
            return Str::of($tableNameWithExtras)
                ->replace(['"', "'", ' '], '')
                ->trim(); // Return only the table name
        }

        return ''; // Return an empty string if Schema::create is not found
    }

    public static function extractColumns(string $content): array
    {
        $relationships = self::extractRelationships($content);
        // Break the content into individual lines.
        $lines = Str::of($content)->explode("\n");

        $columns = [];
        foreach ($lines as $line) {
            $line = Str::of($line)->trim(); // Remove outer whitespace

            // Only process lines that begin with "$table->"
            if (!Str::startsWith($line, '$table->')) {
                continue;
            }

            // Extract the method (i.e. the function name called on $table)
            $method = Str::before(Str::after($line, '$table->'), '(');

            // If the method is exactly "id" or "timestamps", skip them
            if (in_array($method, ['id', 'timestamps'])) {
                continue;
            }

            // If this line is a foreign key declaration, skip it
            if (Str::contains($line, ['foreign', 'references'])) {
                continue;
            }

            // Extract the column name from the first parameter ("('...')")
            $name = Str::between($line, "('", "')");
            if (empty($name)) {
                continue;
            }

            // Check whether the column is defined as nullable
            $isNullable = Str::contains($line, 'nullable()');

            // Get related data
            $is_foreign = false;
            $related_table = null;
            $related_column = null;
            foreach ($relationships as $relationship) {
                if ($relationship['column'] == $name) {
                    $is_foreign = true;
                    $related_table = $relationship['on'];
                    $related_column = $relationship['references'];
                    break;
                }
            }

            $columns[] = [
                'name' => $name,
                'type' => $method,
                'is_nullable' => $isNullable,
                'is_foreign' => $is_foreign,
                'related_table' => $related_table,
                'related_column' => $related_column,
            ];
        }

        return $columns;
    }

    public static function extractRelationships(string $content): array
    {
        // Break the content into lines
        $lines = Str::of($content)->explode("\n");

        $relationships = [];
        foreach ($lines as $line) {
            $line = Str::of($line)->trim(); // Remove whitespace

            // Check if the line defines a relationship
            if (Str::contains($line, '$table->foreign') && Str::contains($line, '->references') && Str::contains($line, '->on')) {
                // Extract the column name inside foreign('...')
                $columnStart = Str::after($line, '$table->foreign(');
                $column = Str::of(Str::before($columnStart, ')'))->replace("'", '')->trim();

                // Extract the referenced column
                $referencesStart = Str::after($line, "->references('");
                $references = Str::before($referencesStart, "')");

                // Extract the referenced table
                $onStart = Str::after($line, "->on('");
                $on = Str::before($onStart, "')");

                // Add the relationship to the array
                $relationships[] = [
                    'column' => $column,
                    'references' => $references,
                    'on' => $on,
                ];
            }
        }

        return $relationships;
    }

}
