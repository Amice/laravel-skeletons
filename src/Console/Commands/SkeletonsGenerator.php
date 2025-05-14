<?php

namespace KovacsLaci\LaravelSkeletons\Console\Commands;

use KovacsLaci\LaravelSkeletons\Services\AbstractGenerator;
use KovacsLaci\LaravelSkeletons\Services\ControllerGenerator;
use KovacsLaci\LaravelSkeletons\Services\MigrationParser;
use KovacsLaci\LaravelSkeletons\Services\ModelGenerator;
use KovacsLaci\LaravelSkeletons\Services\RequestGenerator;
use KovacsLaci\LaravelSkeletons\Services\RouteGenerator;
use KovacsLaci\LaravelSkeletons\Services\SeederGenerator;
use KovacsLaci\LaravelSkeletons\Services\TranslationsGenerator;
use KovacsLaci\LaravelSkeletons\Services\ViewGenerator;
use KovacsLaci\LaravelSkeletons\Services\Views\MenuGenerator;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Class GenerateFiles
 *
 * This command generates the necessary files for a Laravel application based
 * on a given migration file. It processes models, controllers, requests,
 * views, routes, language files, and menus. Additionally, it tracks generated
 * files along with backup files in order to allow a rollback if any generation
 * phase fails.
 *
 * It also provides a cleanup option (--clean-up) that removes all backup (.bak)
 * files within the project.
 *
 * @package App\Console\Commands
 */
class SkeletonsGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-skeletons
        {--migration= : The migration file to be used, e.g. create_products_table}
        {--api : Generetes code for RESTful API}
        {--css-style=plain : The CSS style to apply. Available options: plain, bootstrap, tailwind}
        {--with-auth : Include authentication support in the generated code.}
        {--no-copyright : If set, generated files will omit the copyright header}
        {--cleanup : Remove all .bak files from the folders and exit}
        {--purge : Remove all generated file for given migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the necessary files for a Laravel application based on a migration';

    /**
     * Aggregated results from all generation phases.
     *
     * Contains two keys:
     *   - 'generated_files': Newly created file paths.
     *   - 'backup_files': An associative array mapping original file paths to their backups.
     *
     * @var array
     */
    protected array $allGeneratedFiles = [
        'generated_files' => [],
        'backup_files'    => [],
        'menu_items'      => [],
    ];

    /**
     * Execute the console command.
     *
     * Reads command options to either perform a cleanup of backup files or
     * to proceed with generating application files based on a specified migration.
     * If an error occurs during any generation phase, a rollback is performed.
     *
     * @return void
     */
    public function handle(): void
    {
        $withAuth = $this->option('with-auth');
        $api  = $this->option('api');

        // Clean-up mode: remove all .bak files and exit.
        if ($this->option('cleanup')) {
            $this->cleanupBakFiles();
            return;
        }

        $migrationName = $this->option('migration');
        if (!$migrationName) {
            $this->error('❗Please provide the migration name using --migration= option.');
            return;
        }

//        $logFileName = $this->getLogFileName($migrationName, $api);
//        $logFile = AbstractGenerator::getPath(storage_path("app/skeletons_log/{$logFileName}"));

        if ($this->option('purge')) {
            $this->purgeGeneratedFiles($migrationName, $api);
            return;
        }

        // Retrieve the migration file path using a glob pattern.
        $migrationFilePath = glob(AbstractGenerator::getPath(database_path("migrations/*_$migrationName.php")));
        if (empty($migrationFilePath)) {
            $this->error("❗Migration file '$migrationName' not found.");
            return;
        }

        $cssStyle = $this->getCssStyle();

        // Process the migration file to build a collection of entities.
        $entities = MigrationParser::processMigrationFile($migrationFilePath[0]);

        try {
            // Phase 0: copying language files.
            $copiedFiles = TranslationsGenerator::copySkeletonsLangFiles();
            if (!empty($copiedFiles)) {
                foreach ($copiedFiles as $file) {
                    $this->allGeneratedFiles['generated_files'][] = $file;
                }
                $this->info("✅ Skeletons lang files copied.");
            }

            // Phase 1: Generate Models and Update related models.
            $this->processPhase(ModelGenerator::class, $entities);
            $this->processPhase(ModelGenerator::class, $entities, [], 'updateRelatedModels');
            // Phase 2: Generate Requests.
            $this->processPhase(RequestGenerator::class, $entities, [$api]);
            // Phase 3: Generate Controllers.
            $this->processPhase(ControllerGenerator::class, $entities, [$api]);
            // Phase 5: Generate Routes.
            $this->processPhase(RouteGenerator::class, $entities, [$api, $withAuth]);

            if (!$api) {
                // Phase 4: Generate Views (pass extra parameter for bootstrap support).
                $this->processPhase(ViewGenerator::class, $entities, [$cssStyle, $withAuth]);
                // Phase 6: Generate Language Files.
                $this->processPhase(TranslationsGenerator::class, $entities);
                // Phase 7: Generate Menus.
                $this->processPhase(MenuGenerator::class, $entities, [$cssStyle]);
            }
            // Phase 8: Generate Seeders
            $this->processPhase(SeederGenerator::class, $entities);
        } catch (Exception $e) {
            $this->error($e->getMessage());

            // Rollback: Delete all generated files.
            foreach ($this->allGeneratedFiles['generated_files'] as $file) {
                if (File::exists($file)) {
                    File::delete($file);
                    $this->info("✅ Rolled back file: $file");
                }
            }
            $this->allGeneratedFiles['generated_files'] = [];

            // Rollback: Restore backups.
            foreach ($this->allGeneratedFiles['backup_files'] as $original => $backup) {
                if (File::exists($backup)) {
                    File::move($backup, $original);
                    $this->info("✅ Restored backup for: $original");
                    // Add the restored file to the generated_files list if it's not already there.
                    if (!in_array($original, $this->allGeneratedFiles['generated_files'])) {
                        $this->allGeneratedFiles['generated_files'][] = $original;
                    }
                }
            }
            $this->allGeneratedFiles['backup_files'] = [];

            // Rollback menu items
            $this->removeMenuItems($this->allGeneratedFiles['menu_items']);
            $this->allGeneratedFiles['menu_items'] = [];
        } finally {
            $this->saveGenerationLog($migrationName);
        }
    }

    /**
     * Cleans up all backup (.bak) files from the project base directory.
     *
     * Uses the Symfony Finder to search for all files with a .bak extension
     * and deletes each one found. Outputs a message indicating success or if no backups were found.
     *
     * @return void
     */
    protected function cleanupBakFiles(): void
    {
        $this->info("Starting cleanup of backup (.bak) files...");

        $finder = new Finder();
        $finder->files()->in(base_path())->name('*.bak');

        $deletedCount = 0;
        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            try {
                if (File::exists($filePath)) {
                    File::delete($filePath);
                    $this->info("Deleted backup file: $filePath");
                    $deletedCount++;
                }
            } catch (Exception $e) {
                $this->error("❗Error deleting file $filePath: " . $e->getMessage());
            }
        }

        if ($deletedCount > 0) {
            $this->info("✅ Cleanup complete, $deletedCount backup files removed.");
        } else {
            $this->info("No backup (.bak) files found.");
        }
    }

    /**
     * Merges new generation results into the aggregated results stored in $this->allGeneratedFiles.
     *
     * The result array should be an associative array with keys 'generated_files'
     * and 'backup_files'. This method merges the new arrays into the respective keys.
     *
     * @param array $result An associative array containing generation results.
     *
     * @return void
     */
    protected function updateAllGeneratedFiles(array $result): void
    {
        $this->allGeneratedFiles['generated_files'] = array_merge(
            $this->allGeneratedFiles['generated_files'],
            $result['generated_files'] ?? []
        );
        $this->allGeneratedFiles['backup_files'] = array_merge(
            $this->allGeneratedFiles['backup_files'],
            $result['backup_files'] ?? []
        );
        $this->allGeneratedFiles['menu_items'] = array_merge(
            $this->allGeneratedFiles['menu_items'],
            $result['menu_items'] ?? []
        );
    }

    protected function getLogFileName(string $migrationName, bool $isApi = false): string
    {
        return $migrationName . ($isApi ? '-api' : '') . '.json.log';
    }

    /**
     * Processes a generation phase by instantiating the given generator for each entity,
     * calling the specified method, and merging the results.
     *
     * This method dynamically creates a generator instance for each entity and then
     * calls the generation or update method (as specified by $methodName). Any additional
     * parameters for the generator's constructor can be passed via the $extraParams array.
     *
     * @param string $generatorClass The fully qualified class name of the generator.
     * @param array  $entities       The list of parsed entities.
     * @param array  $extraParams    Additional parameters to pass to the generator’s constructor.
     * @param string $methodName     The method to call on the generator instance (defaults to 'generate').
     *
     * @return void
     */
    protected function processPhase(
        string $generatorClass,
        array $entities,
        array $extraParams = [],
        string $methodName = 'generate'
    ): void {
        foreach ($entities as $entity) {
            // Dynamically instantiate the generator.
            // The constructor receives the current command instance, the entity, and any extra parameters.
            $generator = new $generatorClass($this, $entity, ...$extraParams);
            $result = $generator->$methodName();
            if (!empty($result)) {
                $this->updateAllGeneratedFiles($result);
            }
        }
    }

    /**
     * Purges previously generated files and backups for a given migration.
     *
     * @param string $migrationName The migration name associated with the generated files.
     *
     * @return void
     */
    protected function purgeGeneratedFiles(string $migrationName, bool $isApiPurge = false): void
    {
        $targetLogFile = $this->getLogFileName($migrationName, $isApiPurge);
        $comparisonLogFile = $this->getLogFileName($migrationName, !$isApiPurge);

//        $webLogFile = $logFileBase . '.log';
//        $targetLogFile = $purgeApiOnly ? $apiLogFile : $webLogFile;
//        $comparisonLogFile = $purgeApiOnly ? $webLogFile : $apiLogFile;
//        $isApiPurge = $purgeApiOnly;

        $this->info("Purging generated files for migration: $migrationName (Target log: {$targetLogFile}, Comparison log: {$comparisonLogFile})");

        if (!File::exists($targetLogFile)) {
            $this->info("Target log file not found: {$targetLogFile}. Skipping purge.");
            return;
        }

        $targetLogData = json_decode(File::get($targetLogFile), true);
        $comparisonLogData = File::exists($comparisonLogFile) ? json_decode(File::get($comparisonLogFile), true) : ['generated_files' => [], 'backup_files' => []];

        $targetGeneratedFiles = $targetLogData['generated_files'] ?? [];
        $targetBackupFiles = $targetLogData['backup_files'] ?? [];
        $apiOptionUsed = $targetLogData['options']['api'] ?? false; // Check options of the target log

        if ($isApiPurge && !$apiOptionUsed) {
            $this->info("API files were not generated with the --api option according to the log. Skipping API purge.");
            return;
        }

        $comparisonGeneratedFiles = $comparisonLogData['generated_files'] ?? [];

        // Purge generated files
        $this->purgeFilesByType('generated', $targetGeneratedFiles, $comparisonGeneratedFiles, $isApiPurge, $apiOptionUsed);

        // Purge backup files
        $this->purgeFilesByType('backup', $targetBackupFiles, $comparisonGeneratedFiles, $isApiPurge, $apiOptionUsed, $targetGeneratedFiles);

        // Handle menu items (only during web purge)
        if (!$isApiPurge) {
            $this->removeMenuItems($targetLogData['menu_items'] ?? []);
        }

        File::delete($targetLogFile);
        $this->info("Removed log file: {$targetLogFile}");
        $this->info("✅ Purge process is complete!");
    }

    protected function purgeFilesByType(string $type, array $targetFiles, array $comparisonFiles, bool $isApiPurge, bool $apiOptionUsed, array $targetGeneratedFiles = []): void
    {
        $this->info("Purging {$type} files:");
        foreach ($targetFiles as $file) {
            $shouldDelete = true;
            $originalFilenameForBackup = null;

            if ($type === 'backup') {
                $originalFilenameForBackup = str_replace('.bak', '', $file);
                if (!empty($targetGeneratedFiles) && !in_array($originalFilenameForBackup, $targetGeneratedFiles)) {
                    $shouldDelete = false; // Backup of a non-existent generated file in target
                }
                $fileToCheck = $originalFilenameForBackup;
            } else {
                $fileToCheck = $file;
            }

            if (in_array($fileToCheck, $comparisonFiles)) {
                $shouldDelete = false;
                $this->info("  - Skipping deletion of {$type} file (also in comparison log): {$file}");
            }

            if ($shouldDelete) {
                if ($type === 'generated' && Str::contains($file, 'routes')) {
                    RouteGenerator::removeRequireFromRoutes($file, $isApiPurge);
                }
                if (File::exists($file)) {
                    File::delete($file);
                    $this->info("  - Deleted {$type} file: {$file}");
                }
            }
        }
    }

//    protected function purgeGeneratedFiles(string $migrationName, string $logFileBase, bool $purgeApiOnly = false): void
//    {
//        $targetLogFile = $this->getLogFileName($migrationName, $purgeApiOnly);
//        $comparisonLogFile = $this->getLogFileName($migrationName, !$purgeApiOnly);
//        $this->info("Purging generated files for migration: $migrationName (log file: {$logFile})");
//
//        if (!File::exists($logFile)) {
//            $this->error("No generation log found for migration: $migrationName");
//            return;
//        }
//
//        $logData = json_decode(File::get($logFile), true);
//        $apiOptionUsed = $logData['options']['api'] ?? false;
//
////            if ($purgeApiOnly && !$apiOptionUsed) {
////                $this->info("Skipping purge of API files as they were not generated with the --api option.");
////                return;
////            }
//
//        if (!empty($logData['generated_files'])) {
//            foreach ($logData['generated_files'] as $file) {
//                if (Str::contains($file, 'routes')) {
//                    RouteGenerator::removeRequireFromRoutes($file, $apiOptionUsed);
//                }
//                if (File::exists($file)) {
//                    File::delete($file);
//                    $this->info("Deleted generated file: {$file}");
//                }
//            }
//        }
//        if (!empty($logData['backup_files'])) {
//            foreach ($logData['backup_files'] as $original => $backup) {
//                if (File::exists($backup)) {
//                    File::delete($backup);
//                    $this->info("Deleted backup file: {$backup}");
//                }
//            }
//        }
//        $this->removeMenuItems($logData['menu_items'] ?? []);
//        File::delete($logFile); // Now we can delete the specific log file
//        $this->info("Removed log file: $logFile");
//        $this->info("✅ Purge process is complete!");
//    }

//    protected function purgeGeneratedFiles(string $migrationName, string $logFile, bool $purgeApiOnly = false): void
//    {
//        $this->info("Purging generated files for migration: $migrationName");
//
//        $message = "No generation log found for migration: $migrationName";
//        if (File::exists($logFile)) {
//            $logData = json_decode(File::get($logFile), true);
//            $generatedWithApi = $logData['options']['api'] ?? false; // Check if files were generated with --api
//
//            $this->info("API Option Used During Generation: " . ($generatedWithApi ? 'Yes' : 'No'));
//            $this->info("Purge API Only Option: " . ($purgeApiOnly ? 'Yes' : 'No'));
//
//            // Delete generated files.
//            if (!empty($logData['generated_files'])) {
//                foreach ($logData['generated_files'] as $file) {
//                    $shouldDelete = true;
//
//                    // If --purge --api is used, only delete files that were generated with --api.
//                    if ($purgeApiOnly && !$generatedWithApi) {
//                        $shouldDelete = false;
//                        $this->info("Skipping deletion of: {$file} (not generated with --api)");
//                    }
//
//                    if ($shouldDelete) {
//                        if (Str::contains($file, 'routes')) {
//                            RouteGenerator::removeRequireFromRoutes($file, $generatedWithApi);
//                        }
//                        if (File::exists($file)) {
//                            File::delete($file);
//                            $this->info("Deleted generated file: {$file}");
//                        }
//                    }
//                }
//            }
//            // Delete backup files.
//            if (!empty($logData['backup_files'])) {
//                foreach ($logData['backup_files'] as $original => $backup) {
//                    $shouldDelete = true;
//
//                    // If --purge --api is used, only delete backups of files generated with --api.
//                    // We might not have a direct flag for backups, so we can try to infer based on the original filename.
//                    // This might need more sophisticated logic depending on how you create backups.
//                    if ($purgeApiOnly && !$generatedWithApi) {
//                        $shouldDelete = false;
//                        $this->info("Skipping deletion of backup: {$backup} (original not generated with --api)");
//                    }
//
//                    if ($shouldDelete && File::exists($backup)) {
//                        File::delete($backup);
//                        $this->info("Deleted backup file: {$backup}");
//                    }
//                }
//            }
//            // Delete menu items.
//            if ($purgeApiOnly && !$generatedWithApi) {
//                $this->info("Skipping menu item removal (not generated with --api)");
//            } else {
//                $this->removeMenuItems($logData['menu_items']);
//            }
//
//            // Remove the log file after purge.
//            File::delete($logFile);
//            $message = "Removed log file: $logFile";
//        }
//        $this->info($message);
//
//        // Additionally, clean up any stray .bak files in the project.
//        // $this->cleanupBakFiles();
//
//        $this->info("✅ Purge process is complete!");
//    }

    //    protected function purgeGeneratedFiles(string $migrationName, string $logFile): void
//    {
//        $this->info("Purging all generated files for migration: $migrationName");
//
//        $message = "No generation log found for migration: $migrationName";
//        if (File::exists($logFile)) {
//            $logData = json_decode(File::get($logFile), true);
//            $apiOptionUsed = $logData['options']['api'] ?? false; // Default to false if option not found
//
//            // Delete generated files.
//            if (!empty($logData['generated_files'])) {
//                foreach ($logData['generated_files'] as $file) {
//                    if (Str::contains($file, 'routes')) {
//                        RouteGenerator::removeRequireFromRoutes($file, $apiOptionUsed);
//                    }
//                    if (File::exists($file)) {
//                        File::delete($file);
//                        $this->info("Deleted generated file: {$file}");
//                    }
//                }
//            }
//            // Delete backup files.
//            if (!empty($logData['backup_files'])) {
//                foreach ($logData['backup_files'] as $original => $backup) {
//                    if (File::exists($backup)) {
//                        File::delete($backup);
//                        $this->info("Deleted backup file: {$backup}");
//                    }
//                }
//            }
//            // Delete menu items.
//            $this->removeMenuItems($logData['menu_items']);
//            // Remove the log file after purge.
//            File::delete($logFile);
//            $message = "Removed log file: $logFile";
//        }
//        $this->info($message);
//
//        // Additionally, clean up any stray .bak files in the project.
////        $this->cleanupBakFiles();
//
//        $this->info("✅ Purge process is complete!");
//    }

    /**
     * Save the generation log for the given migration.
     *
     * If a log file already exists, it reads and decodes it to an array,
     * merges it with the current generation data, removes duplicate file entries,
     * and writes the merged data back to the log file.
     *
     * @param string $migrationName The migration name identifier.
     * @param string $logFile The log file name.
     *
     * @return void
     */

    protected function saveGenerationLog(string $migrationName): void
    {
        $logFile = $this->getLogFileName($migrationName, $this->option('api'));
        $newLogData = [
            'generated_files' => $this->allGeneratedFiles['generated_files'] ?? [],
            'backup_files' => $this->allGeneratedFiles['backup_files'] ?? [],
            'menu_items' => $this->allGeneratedFiles['menu_items'] ?? [],
            'options' => [
                'api' => $this->option('api'),
                'with-auth' => $this->option('with-auth'),
                // Add other relevant options if needed
            ],
        ];
        $finalLogData = $newLogData;

        if (File::exists($logFile)) {
            $existing = json_decode(File::get($logFile), true);
            if (!is_array($existing)) {
                $existing = ['generated_files' => [], 'backup_files' => [], 'menu_items' => [], 'options' => []];
            }
            $existing['generated_files'] = array_values(array_unique(array_merge($existing['generated_files'], $newLogData['generated_files'])));
            $existing['backup_files'] = array_values(array_unique(array_merge($existing['backup_files'], $newLogData['backup_files'])));
            $existing['menu_items'] = array_values(array_unique(array_merge($existing['menu_items'], $newLogData['menu_items'])));
            $existing['options'] = $newLogData['options'];
            $finalLogData = $existing;
        }

        File::ensureDirectoryExists(File::dirname($logFile));
        File::put($logFile, json_encode($finalLogData, JSON_PRETTY_PRINT));
        $this->info("✅ Generation log saved to {$logFile}");
    }

    //    protected function saveGenerationLog(string $migrationName, string $logFile): void
//    {
//        // Initialize the new log data structure
//        $newLogData = [
//            'web' => [
//                'generated_files' => $this->allGeneratedFiles['web']['generated_files'] ?? [],
//                'backup_files' => $this->allGeneratedFiles['web']['backup_files'] ?? [],
//                'menu_items' => $this->allGeneratedFiles['web']['menu_items'] ?? [],
//            ],
//            'api' => [
//                'generated_files' => $this->allGeneratedFiles['api']['generated_files'] ?? [],
//                'backup_files' => $this->allGeneratedFiles['api']['backup_files'] ?? [],
//            ],
//            'options' => [
//                'api' => $this->option('api'),
//                'with-auth' => $this->option('with-auth'),
//                // Add other relevant options if needed
//            ],
//        ];
//
//        $finalLogData = $newLogData;
//
//        // If the log file exists, merge its data with the new one.
//        if (File::exists($logFile)) {
//            $existing = json_decode(File::get($logFile), true);
//            if (!is_array($existing)) {
//                $existing = [
//                    'web' => ['generated_files' => [], 'backup_files' => [], 'menu_items' => []],
//                    'api' => ['generated_files' => [], 'backup_files' => []],
//                    'options' => [],
//                ];
//            }
//
//            // Merge web data
//            $existing['web']['generated_files'] = array_values(array_unique(array_merge(
//                $existing['web']['generated_files'] ?? [],
//                $newLogData['web']['generated_files'] ?? []
//            )));
//            $existing['web']['backup_files'] = array_values(array_unique(array_merge(
//                $existing['web']['backup_files'] ?? [],
//                $newLogData['web']['backup_files'] ?? []
//            )));
//            $existing['web']['menu_items'] = array_values(array_unique(array_merge(
//                $existing['web']['menu_items'] ?? [],
//                $newLogData['web']['menu_items'] ?? []
//            )));
//
//            // Merge api data
//            $existing['api']['generated_files'] = array_values(array_unique(array_merge(
//                $existing['api']['generated_files'] ?? [],
//                $newLogData['api']['generated_files'] ?? []
//            )));
//            $existing['api']['backup_files'] = array_values(array_unique(array_merge(
//                $existing['api']['backup_files'] ?? [],
//                $newLogData['api']['backup_files'] ?? []
//            )));
//
//            // Keep the options from the latest generation
//            $existing['options'] = $newLogData['options'];
//
//            $finalLogData = $existing;
//        }
//
//        // Save the merged log back to the file.
//        File::ensureDirectoryExists(File::dirname($logFile));
//        File::put($logFile, json_encode($finalLogData, JSON_PRETTY_PRINT));
//        $this->info("Generation log updated: {$logFile}");
//    }
    //    protected function saveGenerationLog(string $migrationName, string $logFile): void
//    {
//        // Get current generation log data.
//        $newLogData = $this->allGeneratedFiles;
//        $finalLogData = $newLogData;
//
//        // Add the command options to the new log data
//        $newLogData['options'] = [
//            'api' => $this->option('api'),
//            'with-auth' => $this->option('with-auth'),
//            // Add other relevant options if needed
//        ];
//
//        // If the log file exists, merge its data with the new one.
//        if (File::exists($logFile)) {
//            $existing = json_decode(File::get($logFile), true);
//            if (!is_array($existing)) {
//                $existing = [
//                    'generated_files' => [],
//                    'backup_files'    => [],
//                    'options' => [], // Initialize options if not present
//                ];
//            }
//
//            // Merge generated and backup files and remove duplicates.
//            $mergedGeneratedFiles = array_merge($existing['generated_files'], $newLogData['generated_files']);
//            $mergedBackupFiles    = array_merge($existing['backup_files'], $newLogData['backup_files']);
//
//            $existing['generated_files'] = array_values(array_unique($mergedGeneratedFiles));
//            $existing['backup_files']    = array_values(array_unique($mergedBackupFiles));
//
//            // Keep the options from the latest generation
//            $existing['options'] = $newLogData['options'];
//
//            $finalLogData = $existing;
//        } else {
//            $finalLogData['options'] = $newLogData['options'];
//        }
//
//        // Save the merged log back to the file.
//        File::ensureDirectoryExists(File::dirname($logFile));
//        File::put($logFile, json_encode($finalLogData, JSON_PRETTY_PRINT));
//        $this->info("Generation log updated: {$logFile}");
//    }

    private function removeMenuItem(string $navFilePath, string $content, string $menuItem): void
    {
        // Remove the menu item from the content
        if (Str::contains($content, $menuItem)) {
            $content = Str::replaceFirst("$menuItem\n", '', $content); // Ensure proper indentation handling
            File::put($navFilePath, $content);
            $this->info("✅ Menu item successfully removed from nav.blade.php.");
        } else {
            $this->warn("❗Menu item not found in nav.blade.php. Rollback skipped.");
        }
    }

    private function removeMenuItems($menuItems): void
    {
        if (!empty($menuItems)) {
            $navFilePath = AbstractGenerator::getPath(resource_path('views/layouts/nav.blade.php'));
            // Ensure nav.blade.php exists
            if (!File::exists($navFilePath)) {
                $this->warn("❗nav.blade.php not found. Cannot perform rollback.");
                return;
            }
//            $content = File::get($navFilePath);
            foreach ($menuItems as $menuItem) {
                $content = File::get($navFilePath);
                $this->removeMenuItem($navFilePath, $content, $menuItem);
            }
        }
    }

    private function getCssStyle()
    {
        $cssStyle = trim($this->option('css-style'));
        if (empty($cssStyle)) {
            return '';
        }
        return $cssStyle;
    }
}
