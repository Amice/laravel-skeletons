<?php

namespace App\Console\Commands;

use App\Services\AbstractGenerator;
use App\Services\ControllerGenerator;
use App\Services\ModelGenerator;
use App\Services\RequestGenerator;
use App\Services\MigrationParser;
use App\Services\RouteGenerator;
use App\Services\TranslationsGenerator;
use App\Services\ViewGenerator;
use App\Services\MenuGenerator;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Class SkeletonsGenerator
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
 * @author László Kovács
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
        {--clean-up : Remove all .bak files from the folders and exit}';

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
        if ($this->option('clean-up')) {
            $this->cleanupBakFiles();
            return;
        }

        $migrationName = $this->option('migration');
        if (!$migrationName) {
            $this->error('Please provide the migration name using --migration= option.');
            return;
        }

        $withBootStrap = $this->option('with-bootstrap');

        // Retrieve the migration file path using a glob pattern.
        $migrationFilePath = glob(AbstractGenerator::getPath(database_path("migrations/*_$migrationName.php")));
        if (empty($migrationFilePath)) {
            $this->error("Migration file '$migrationName' not found.");
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
            $this->processPhase(MenuGenerator::class, $entities);
        } catch (Exception $e) {
            $this->error($e->getMessage());

            // Rollback: Delete all generated files.
            foreach ($this->allGeneratedFiles['generated_files'] as $file) {
                if (File::exists($file)) {
                    File::delete($file);
                    $this->info("Rolled back file: $file");
                }
            }
            // Rollback: Restore backups.
            foreach ($this->allGeneratedFiles['backup_files'] as $original => $backup) {
                if (File::exists($backup)) {
                    File::move($backup, $original);
                    $this->info("Restored backup for: $original");
                }
            }
            return;
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
                $this->error("Error deleting file $filePath: " . $e->getMessage());
            }
        }

        if ($deletedCount > 0) {
            $this->info("Cleanup complete, $deletedCount backup files removed.");
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
}
