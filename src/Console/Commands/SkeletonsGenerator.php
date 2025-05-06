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
        {--with-bootstrap : Add this option if you want the views to be generated with Bootstrap support}
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

        $logFile = AbstractGenerator::getPath(storage_path("app/skeletons_log/$migrationName.json"));
        if ($this->option('purge')) {
            $this->purgeGeneratedFiles($migrationName, $logFile);
            return;
        }

        $withBootStrap = $this->option('with-bootstrap');

        // Retrieve the migration file path using a glob pattern.
        $migrationFilePath = glob(AbstractGenerator::getPath(database_path("migrations/*_$migrationName.php")));
        if (empty($migrationFilePath)) {
            $this->error("❗Migration file '$migrationName' not found.");
            return;
        }

        // Process the migration file to build a collection of entities.
        $entities = MigrationParser::processMigrationFile($migrationFilePath[0]);

        try {
            // Phase 1: Generate Models and Update related models.
            $this->processPhase(ModelGenerator::class, $entities);
            $this->processPhase(ModelGenerator::class, $entities, [], 'updateRelatedModels');

            // Phase 2: Generate Requests.
            $this->processPhase(RequestGenerator::class, $entities);

            // Phase 3: Generate Controllers.
            $this->processPhase(ControllerGenerator::class, $entities);

            // Phase 4: Generate Views (pass extra parameter for bootstrap support).
            $this->processPhase(ViewGenerator::class, $entities, [$withBootStrap]);

            // Phase 5: Generate Routes.
            $this->processPhase(RouteGenerator::class, $entities);

            // Phase 6: Generate Language Files.
            $this->processPhase(TranslationsGenerator::class, $entities);

            // Phase 7: Generate Menus.
            $this->processPhase(MenuGenerator::class, $entities, [$withBootStrap]);

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
            $this->saveGenerationLog($migrationName, $logFile);
            $this->info("✅ Generation log saved to {$logFile}");
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
     * @param string $logFile The log file name.
     *
     * @return void
     */
    protected function purgeGeneratedFiles(string $migrationName, string $logFile): void
    {
        $this->info("Purging all generated files for migration: $migrationName");

        $message = "No generation log found for migration: $migrationName";
        if (File::exists($logFile)) {
            $filesLog = json_decode(File::get($logFile), true);

            // Delete generated files.
            if (!empty($filesLog['generated_files'])) {
                foreach ($filesLog['generated_files'] as $file) {
                    if (Str::contains($file, 'routes')) {
                        RouteGenerator::removeRequireFromWebRoutes($file);
                    }
                    if (File::exists($file)) {
                        File::delete($file);
                        $this->info("Deleted generated file: {$file}");
                    }
                }
            }
            // Delete backup files.
            if (!empty($filesLog['backup_files'])) {
                foreach ($filesLog['backup_files'] as $original => $backup) {
                    if (File::exists($backup)) {
                        File::delete($backup);
                        $this->info("Deleted backup file: {$backup}");
                    }
                }
            }
            // Delete menu items.
            $this->removeMenuItems($filesLog['menu_items']);
            // Remove the log file after purge.
            File::delete($logFile);
            $message = "Removed log file: $logFile";
        }
        $this->info($message);

        // Additionally, clean up any stray .bak files in the project.
//        $this->cleanupBakFiles();

        $this->info("✅ Purge process is complete!");
    }

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
    protected function saveGenerationLog(string $migrationName, string $logFile): void
    {
        // Get current generation log data.
        $newLogData = $this->allGeneratedFiles;
        $finalLogData = $newLogData;

        // If the log file exists, merge its data with the new one.
        if (File::exists($logFile)) {
            $existing = json_decode(File::get($logFile), true);
            if (!is_array($existing)) {
                $existing = [
                    'generated_files' => [],
                    'backup_files'    => [],
                ];
            }

            // Merge and remove duplicates.
            $mergedGeneratedFiles = array_merge($existing['generated_files'], $newLogData['generated_files']);
            $mergedBackupFiles    = array_merge($existing['backup_files'], $newLogData['backup_files']);

            // Ensure the arrays contain unique entries.
            $existing['generated_files'] = array_values(array_unique($mergedGeneratedFiles));
            $existing['backup_files']    = array_values(array_unique($mergedBackupFiles));

            $finalLogData = $existing;
        }

        // Save the merged log back to the file.
        File::ensureDirectoryExists(File::dirname($logFile));
        File::put($logFile, json_encode($finalLogData));
        $this->info("Generation log updated: {$logFile}");
    }

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


}
