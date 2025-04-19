<?php

namespace KovacsLaci\LaravelSkeletons\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SkeletonsGenerator extends Command
{
    const BASE_PATH = 'vendor/kovacs-laci/laravel-skeletons/';
    protected $signature = 'app:make-skeletons {singular} {plural}
                           {--with-auth : Routes will be generated with middleware}
                           {--resource : Routes will be generated as resource}
                           {--force : Overwrite all existing files}
                           {--with-backup : Backup files before overwriting}
                           {--drop : Delete all generated files}
                           {--purge : Delete all generated files including .bak files}';

    protected $description = 'Generate or remove Controller, Model, Request, Migration, Seeder, Views, and Routes for a given model.';

    private $templatesPath;

    private $basePath;

    function __construct($basePath = self::BASE_PATH, $templatesPath = 'resources/stubs/')
    {
        parent::__construct();
        $this->basePath = str_replace('/', DIRECTORY_SEPARATOR, $basePath);
        $this->templatesPath = base_path($this->basePath) . str_replace('/', DIRECTORY_SEPARATOR, $templatesPath);
    }

    public function handle()
    {
        $singular = Str::lower($this->argument('singular'));
        $plural = Str::lower($this->argument('plural'));
        $model = Str::studly($singular);

        if ($this->option('purge')) {
            $this->purgeFiles($model, $singular, $plural);
            return;
        }

        if ($this->option('drop')) {
            $this->dropFiles($model, $singular, $plural);
            return;
        }

        $this->info("Generating files for: $model ($singular / $plural)...");
        Log::info("Generating files for: $model");

        $this->generateController($model, $singular, $plural);
        $this->generateModel($model, $plural);
        $this->generateRequest($model);
        $this->generateMigration($singular, $plural);
        $this->generateSeeder($model, $plural);
        $this->generateViews($singular, $plural);
        $this->info("✅ All files generated successfully!");

        $result = $this->addRoutes($singular, $plural);
        if ($result) {
            $this->info("✅ web.php has been updated successfully!");
        }

        $this->copyLocalizationFiles();
        $this->info("✅ Language files have been created successfully!");

        $this->output->writeln("❗ To create the database table(s) don't forget to run command: <fg=yellow>php artisan migrate</>");
    }

    protected function generateController($model, $singular, $plural)
    {
        $destination = str_replace('/', DIRECTORY_SEPARATOR, app_path("Http/Controllers/{$model}Controller.php"));
        $source = str_replace('/', DIRECTORY_SEPARATOR, $this->templatesPath . 'controller.stub');
        $this->createFile(
            $destination,
            $source,
            ['{{model}}' => $model, '{{singular}}' => $singular, '{{plural}}' => $plural]
        );
    }

    protected function generateModel($model, $plural)
    {
        $destination = str_replace('/', DIRECTORY_SEPARATOR, app_path("Models/{$model}.php"));
        $source = str_replace('/', DIRECTORY_SEPARATOR, $this->templatesPath . 'model.stub');
        $this->createFile(
            $destination,
            $source,
            ['{{model}}' => $model, '{{plural}}' => $plural]
        );
    }

    protected function generateRequest($model)
    {
        $destination = str_replace('/', DIRECTORY_SEPARATOR, app_path("Http/Requests/{$model}Request.php"));
        $source = str_replace('/', DIRECTORY_SEPARATOR, $this->templatesPath . 'request.stub');
        $this->createFile(
            $destination,
            $source,
            ['{{model}}' => $model]
        );
    }

    protected function generateMigration($singular, $plural)
    {
        $migrationsPath = database_path("migrations" . DIRECTORY_SEPARATOR);
        $existingMigrations = glob($migrationsPath . "*_create_{$plural}_table.php");
        if (!empty($existingMigrations)) {
            $filename = reset($existingMigrations);
            if ($this->option('with-backup')) {
                File::move($filename, "$filename.bak");
                $this->info("Backup created: {$filename}.bak");
            }
            if ($this->option('force')) {
                File::delete($filename);
            } else {
                $this->warn("Skipping migration: Migration file already exists.");
                return;
            }
        } else {
            $timestamp = date('Y_m_d_His');
            $filename = $migrationsPath . "{$timestamp}_create_{$plural}_table.php";
            $this->createFile(
                $filename,
                $this->templatesPath . 'migration.stub',
                ['{{plural}}' => $plural]
            );
        }

    }

    protected function generateSeeder($model, $plural)
    {
        $destination = str_replace('/', DIRECTORY_SEPARATOR, database_path("seeders/{$model}Seeder.php"));
        $source = str_replace('/', DIRECTORY_SEPARATOR, $this->templatesPath . 'seeder.stub');
        $this->createFile(
            $destination,
            $source,
            ['{{model}}' => $model, '{{plural}}' => $plural]
        );
    }

    protected function generateViews($singular, $plural)
    {
        $viewPath = str_replace('/', DIRECTORY_SEPARATOR, resource_path("views/{$plural}"));
        File::makeDirectory($viewPath, 0777, true, true);

        foreach (['index', 'create', 'edit', 'show'] as $view) {
            $destination = str_replace('/', DIRECTORY_SEPARATOR, "$viewPath/{$view}.blade.php");
            $source = str_replace('/', DIRECTORY_SEPARATOR, $this->templatesPath . "views/{$view}.stub");
            $this->createFile(
                $destination,
                $source,
                ['{{singular}}' => $singular, '{{plural}}' => $plural]
            );
        }
        $this->generateLayoutsViews($singular, $plural);
    }

    private function generateLayoutsViews($singular, $plural)
    {
        $layoutsPath = str_replace('/', DIRECTORY_SEPARATOR, resource_path("views/layouts"));
        File::ensureDirectoryExists($layoutsPath);
        $sourceFies = [
            'app.example.stub',
            'error.stub',
            'search.stub',
            'success.stub',
        ];
        foreach ($sourceFies as $file) {
            $sourceFile = str_replace('/', DIRECTORY_SEPARATOR, $this->templatesPath . "views/layouts/$file");
            $destinationFile = $layoutsPath . DIRECTORY_SEPARATOR . str_replace("stub", "blade.php", $file);
            if (! File::exists($destinationFile)) {
                File::copy($sourceFile, $destinationFile);
            }
        }
    }

    private function addRoutes(string $singular, string $plural)
    {
        $webPhpPath = str_replace('/', DIRECTORY_SEPARATOR, base_path('routes/web.php'));
        if (!File::exists($webPhpPath)) {
            $errorMsg = __('skeletons.file_not_found', ['file' => $webPhpPath]);
            $this->error($errorMsg);
            Log::error("$webPhpPath not found.");
            return false;
        }
        $routeDefinition = $this->getRouteDefinitions($singular, $plural);
        // Read the file contents
        $webPhpContent = File::get($webPhpPath);

//        $controllerClass = "App\\Http\\Controllers\\" . ucfirst($singular) . "Controller";
        $useStatement = $this->getUseStatement($singular);

        // Check if the `use` statement already exists to avoid duplication

        if (!str_contains($webPhpContent, $useStatement)) {
            $webPhpContent = str_replace("<?php", "<?php\n$useStatement\n", $webPhpContent);
        }

        if (str_contains($webPhpContent, $routeDefinition)) {
            $this->info("Route already exists in web.php: $routeDefinition");
            Log::info("Route already exists: $routeDefinition");
            return null;
        }

        $webPhpContent .= "\n" . $routeDefinition . "\n";
        File::put($webPhpPath, $webPhpContent);

        return true;
    }

    private function getUseStatement($singular)
    {
        return  "use App\\Http\\Controllers\\" . Str::studly($singular) . "Controller;";
    }

    protected function copyLocalizationFiles()
    {
        $sourcePath = $this->basePath . 'resources' . DIRECTORY_SEPARATOR . 'lang';
        $destinationPath = resource_path('lang');

        // Ensure the source directory exists
        if (File::isDirectory($sourcePath)) {
            // Get all files and subdirectories from the source
            $items = File::allFiles($sourcePath);

            foreach ($items as $file) {
                $relativePath = $file->getRelativePathname(); // Get the relative path of the file
                $destinationFile = $destinationPath . DIRECTORY_SEPARATOR . $relativePath;

                // Check if the file already exists in the destination
                if (!File::exists($destinationFile)) {
                    // Ensure the destination subdirectory exists
                    File::ensureDirectoryExists(dirname($destinationFile));

                    // Copy the file
                    File::copy($file->getPathname(), $destinationFile);
                }
            }
        }
    }

    protected function createFile($filePath, $stubPath, $replacements)
    {
        $directory = dirname($filePath);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($filePath) && !$this->option('force')) {
            $this->warn("Skipping: {$filePath} already exists.");
            return;
        }

        if ($this->option('with-backup') && File::exists($filePath)) {
            File::move($filePath, $filePath . '.bak');
            $this->info("Backup created: {$filePath}.bak");
        }

        $stub = File::get($stubPath);
        $content = str_replace(array_keys($replacements), array_values($replacements), $stub);
        File::put($filePath, $content);
        $this->info("✔ Created: {$filePath}");
    }

    protected function purgeFiles($model, $singular, $plural)
    {
        $this->dropFiles($model, $singular, $plural, true);
    }

    protected function dropFiles($model, $singular, $plural, $purge = false)
    {
        $files = [
            app_path("Http/Controllers/{$model}Controller.php"),
            app_path("Models/{$model}.php"),
            app_path("Http/Requests/{$model}Request.php"),
            database_path("seeders/{$model}Seeder.php"),
        ];

        foreach ($files as $file) {
            $file = str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (File::exists($file)) {
                File::delete($file);
                $this->info("❌ Deleted: {$file}");
            }
            if ($purge) {
                $bakFile = $file . '.bak';
                if (File::exists($bakFile)) {
                    File::delete($bakFile);
                    $this->info("❌ Deleted: {$bakFile}");
                }
            }
        }

        self::deleteMigrations($plural, $purge);
        self::deleteViews($plural, $purge);
        self::deleteLanguages($purge);
        self::removeRoutes($singular);
    }

    private function deleteMigrations($plural, $purge)
    {
        foreach (glob(database_path("migrations/*_create_{$plural}_table.php")) as $migration) {
            if (File::exists($migration)) {
                File::delete($migration);
                $this->info("❌ Deleted: {$migration}");
            }
            if ($purge) {
                $bakFile = $migration . '.bak';
                if (File::exists($bakFile)) {
                    File::delete($bakFile);
                    $this->info("❌ Deleted: {$bakFile}");
                }
            }
        }
    }

    private function deleteViews($plural, $purge)
    {
        $viewPath = resource_path("views" . DIRECTORY_SEPARATOR . $plural);
        if (File::exists($viewPath)) {
            if ($purge) {
                File::deleteDirectory($viewPath);
                $this->info("❌ Deleted views directory: $viewPath");
            } else {
                $viewFiles = glob($viewPath . DIRECTORY_SEPARATOR . "*.blade.php");
                foreach ($viewFiles as $viewFile) {
                    File::delete($viewFile);
                    $this->info("❌ Deleted: {$viewFile}");
                }
            }
        }
    }

    private function deleteLanguages($purge, $languages = ['en', 'hu'])
    {
        foreach ($languages as $language) {
            $langSkeletonsPath = resource_path("lang" . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . "skeletons.php");
            if (File::exists($langSkeletonsPath)) {
                File::delete($langSkeletonsPath);
                $this->info("❌ Deleted language file: $langSkeletonsPath");
                if ($purge) {
                    $bakFile = $langSkeletonsPath . '.bak';
                    if (File::exists($bakFile)) {
                        File::delete($bakFile);
                        $this->info("❌ Deleted: {$bakFile}");
                    }
                }
            }
        }
    }

    private function removeRoutes(string $singular)
    {
        $webPhpPath = str_replace('/', DIRECTORY_SEPARATOR, base_path('routes/web.php'));
        if (!File::exists($webPhpPath)) {
            $msg = "$webPhpPath not found.";
            $this->info($msg);
            Log::info($msg);
            return;
        }

        $ucSingular = ucfirst($singular);
        $controllerClass = "{$ucSingular}Controller";

        // Open the file and read line by line
        $lines = File::lines($webPhpPath);
        $filteredLines = [];
        foreach ($lines as $line) {
            // Add the line only if it doesn't contain the search string
            if (strpos($line, $controllerClass) === false) {
                $filteredLines[] = $line;
            }
        }

        if (count($filteredLines) < count($lines)) {
            // Save the filtered content back to the file
            File::put($webPhpPath, implode(PHP_EOL, $filteredLines));
            $this->info("Routes from web.php removed.");
        }
    }

    private function getRouteDefinitions(string $singular, string $plural)
    {
        $model = Str::studly($singular);
        $controllerClass = "{$model}Controller::class";

        if ($this->option('with-auth')) {
            $routeDefinition = "Route::middleware('auth')->group(function () {\n";
            $routeDefinition .= "    Route::get('$plural/create', [{$controllerClass}, 'create'])->name('$plural.create');\n";
            $routeDefinition .= "    Route::post('$plural', [{$controllerClass}, 'store'])->name('$plural.store');\n";
            $routeDefinition .= "    Route::get('$plural/{$singular}/edit', [{$controllerClass}, 'edit'])->name('$plural.edit');\n";
            $routeDefinition .= "    Route::put('$plural/{$singular}', [{$controllerClass}, 'update'])->name('$plural.update');\n";
            $routeDefinition .= "    Route::delete('$plural/{$singular}', [{$controllerClass}, 'destroy'])->name('$plural.destroy');\n";
            $routeDefinition .= "});\n";
            $routeDefinition .= "Route::post('/$plural/search', [$controllerClass, 'search'])->name('$plural.search');\n";
            $routeDefinition .= "Route::resource('$plural', {$controllerClass})->except(['create', 'store', 'edit', 'update', 'destroy']);\n";

            return $routeDefinition;
        }

        if ($this->option('resource')) {
            $routeDefinition = "Route::post('/$plural/search', [$controllerClass, 'search'])->name('$plural.search');\n";
            $routeDefinition .= "Route::resource('$plural', {$controllerClass});";

            return $routeDefinition;
        }

        $routeDefinition = "Route::post('/$plural', [$controllerClass, 'store'])->name('$plural.store');\n";
        $routeDefinition .= "Route::get('/$plural/create', [$controllerClass, 'create'])->name('$plural.create');\n";
        $routeDefinition .= "Route::put('/$plural/{{$singular}}', [$controllerClass, 'update'])->name('$plural.update');\n";
        $routeDefinition .= "Route::get('/$plural/{{$singular}}/edit', [$controllerClass, 'edit'])->name('$plural.edit');\n";
        $routeDefinition .= "Route::delete('/$plural/{{$singular}}', [$controllerClass, 'destroy'])->name('$plural.destroy');\n";
        $routeDefinition .= "Route::get('/$plural', [$controllerClass, 'index'])->name('$plural.index');\n";
        $routeDefinition .= "Route::get('/$plural/{{$singular}}', [$controllerClass, 'show'])->name('$plural.show');\n";
        $routeDefinition .= "Route::post('/$plural/search', [$controllerClass, 'search'])->name('$plural.search');\n";

        return $routeDefinition;
    }
}
