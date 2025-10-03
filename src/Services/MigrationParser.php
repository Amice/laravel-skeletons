<?php

namespace KovacsLaci\LaravelSkeletons\Services;

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
        $hasTimestamps = Str::contains($content, '$table->timestamps');

        return compact('tableName', 'columns', 'relationships', 'hasTimestamps');
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
            throw new \Exception("❗Related migrations not found for tables: " . implode(', ', $missing) . ". Halting the process.");
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
        $lines = Str::of($content)->explode("\n");

        $columns = [];
        foreach ($lines as $line) {
            $line = Str::of($line)->trim();

            if (!self::isParsableColumnLine($line)) {
                continue;
            }

            $method = self::extractMethodName($line);

            // Skip utility methods like id() or timestamps(), and explicit foreign key declarations.
            if (self::shouldSkipMethod($method) || self::isExplicitForeignKey($line)) {
                continue;
            }

            $name = self::extractColumnName($line, $method);

            if (empty($name)) {
                continue;
            }

            $columns[] = self::buildColumnData($line, $name, $method, $relationships);
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

    /**
     * Checks if a line is a potential column definition.
     */
    private static function isParsableColumnLine(string $line): bool
    {
        return Str::startsWith($line, '$table->');
    }

    /**
     * Extracts the method name (data type) from the column definition line.
     */
    private static function extractMethodName(string $line): string
    {
        return Str::before(Str::after($line, '$table->'), '(');
    }

    /**
     * Determines if the method should be skipped (e.g., 'id' or 'timestamps').
     */
    private static function shouldSkipMethod(string $method): bool
    {
        return in_array($method, ['id', 'timestamps']);
    }

    /**
     * Determines if the line is an explicit foreign key declaration.
     */
    private static function isExplicitForeignKey(string $line): bool
    {
        return Str::contains($line, ['foreign', 'references']);
    }

    /**
     * Extracts the column name using string manipulation, handling quoted parameters.
     */
    private static function extractColumnName(string $line, string $method): ?string
    {
        // Isolate parameters: e.g., 'name',30);
        $startOfParams = Str::after($line, '$table->' . $method . '(');
        $quotedString = ltrim($startOfParams);

        // Determine the quote type
        if (Str::startsWith($quotedString, "'")) {
            $quote = "'";
        } elseif (Str::startsWith($quotedString, '"')) {
            $quote = '"';
        } else {
            return null; // Cannot reliably parse the column name
        }

        // Extract the name between the first pair of quotes
        $name = Str::between($quotedString, $quote, $quote);

        return empty($name) ? null : $name;
    }

    /**
     * Builds the final column data array, including foreign key information.
     */
    private static function buildColumnData(string $line, string $name, string $method, array $relationships): array
    {
        $isNullable = Str::contains($line, 'nullable()');

        $is_foreign = false;
        $related_table = null;
        $related_column = null;

        // Check if this column is defined as a relationship
        foreach ($relationships as $relationship) {
            if ($relationship['column'] == $name) {
                $is_foreign = true;
                $related_table = $relationship['on'];
                $related_column = $relationship['references'];
                break;
            }
        }

        return [
            'name' => $name,
            'type' => $method,
            'is_nullable' => $isNullable,
            'is_foreign' => $is_foreign,
            'related_table' => $related_table,
            'related_column' => $related_column,
        ];
    }

}
