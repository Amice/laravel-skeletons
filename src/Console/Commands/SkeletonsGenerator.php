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
                           {--force : Overwrite all existing files}
                           {--with-backup : Backup files before overwriting}
                           {--drop : Delete all generated files}
                           {--purge : Delete all generated files including .bak files}';

    protected $description = 'Generate or remove Controller, Model, Request, Migration, Seeder, Views, and Routes for a given model.';

    private $templatesPath = self::BASE_PATH . 'resources/stubs/';

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

        $this->addRoutes($singular, $plural);
        $this->info("✅ web.php has been updated successfully!");

        $this->copyLocalizationFiles();
        $this->info("✅ Language files have been created successfully!");
    }

    protected function generateController($model, $singular, $plural)
    {
        $this->createFile(
            app_path("Http/Controllers/{$model}Controller.php"),
            base_path($this->templatesPath . 'controller.stub'),
            ['{{model}}' => $model, '{{singular}}' => $singular, '{{plural}}' => $plural]
        );
    }

    protected function generateModel($model, $plural)
    {
        $this->createFile(
            app_path("Models/{$model}.php"),
            base_path($this->templatesPath . 'model.stub'),
            ['{{model}}' => $model, '{{plural}}' => $plural]
        );
    }

    protected function generateRequest($model)
    {
        $this->createFile(
            app_path("Http/Requests/{$model}Request.php"),
            base_path($this->templatesPath . 'request.stub'),
            ['{{model}}' => $model]
        );
    }

    protected function generateMigration($singular, $plural)
    {
        $existingMigrations = glob(database_path("migrations/*_create_{$plural}_table.php"));
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
            $filename = database_path("migrations/{$timestamp}_create_{$plural}_table.php");
            $this->createFile(
                $filename,
                base_path($this->templatesPath . 'migration.stub'),
                ['{{plural}}' => $plural]
            );
        }

    }


    protected function generateSeeder($model, $plural)
    {
        $this->createFile(
            database_path("seeders/{$model}Seeder.php"),
            base_path($this->templatesPath . 'seeder.stub'),
            ['{{model}}' => $model, '{{plural}}' => $plural]
        );
    }

    protected function generateViews($singular, $plural)
    {
        $viewPath = resource_path("views/{$plural}");
        File::makeDirectory($viewPath, 0777, true, true);

        foreach (['index', 'create', 'edit', 'show'] as $view) {
            $this->createFile(
                "$viewPath/{$view}.blade.php",
                base_path($this->templatesPath . "views/{$view}.stub"),
                ['{{singular}}' => $singular, '{{plural}}' => $plural]
            );
        }

        $sourceFile = base_path($this->templatesPath . "views/layout.example.stub");
        $layoutsPath = resource_path("views" .DIRECTORY_SEPARATOR. "layouts");
        $destinationFile = $layoutsPath . DIRECTORY_SEPARATOR . "example.app.blade.php";
        File::ensureDirectoryExists($layoutsPath);
        File::copy($sourceFile, $destinationFile);
    }

    protected function copyLocalizationFiles()
    {
        $sourcePath = self::BASE_PATH . 'resources' . DIRECTORY_SEPARATOR . 'lang';
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
        self::deleteViews($singular, $purge);
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
    private function addRoutes(string $singular, string $plural)
    {
        $webPhpPath = base_path('routes' . DIRECTORY_SEPARATOR . 'web.php');
        if (!File::exists($webPhpPath)) {
            $this->error("web.php not found.");
            Log::error("web.php not found.");
            return;
        }
        $routeDefinition = $this->getRouteDefinitions($singular, $plural);
        // Read the file contents
        $webPhpContent = File::get($webPhpPath);

        $controllerClass = "App\\Http\\Controllers\\" . ucfirst($singular) . "Controller";
        $useStatement = "use $controllerClass;";

        // Check if the `use` statement already exists to avoid duplication
        if (!str_contains($webPhpContent, $useStatement)) {
            $webPhpContent = str_replace("<?php\n", "<?php\n\n$useStatement\n", $webPhpContent);
        }

        if (str_contains($webPhpContent, $routeDefinition)) {
            $this->info("Route already exists in web.php: $routeDefinition");
            Log::info("Route already exists: $routeDefinition");
            return;
        }

        $webPhpContent .= "\n" . $routeDefinition . "\n";
        File::put($webPhpPath, $webPhpContent);
        $this->info("Added route to web.php: $routeDefinition");
        Log::info("Added new route: $routeDefinition");
    }

    private function removeRoutes(string $singular)
    {
        $webPhpPath = base_path('routes' . DIRECTORY_SEPARATOR . 'web.php');
        if (!File::exists($webPhpPath)) {
            $msg = "$webPhpPath not found.";
            $this->info($msg);
            Log::info($msg);
            return;
        }

        $ucSingular = ucfirst($singular);
        $controllerClass = "{$ucSingular}Controller::class";

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
        $ucSingular = ucfirst($singular);
        $controllerClass = "{$ucSingular}Controller::class";

        if ($this->option('with-auth')) {
            $routeDefinition = "Route::middleware('auth')->group(function () {\n";
            $routeDefinition .= "    Route::get('$plural/create', [{$controllerClass}, 'create'])->name('$plural.create');\n";
            $routeDefinition .= "    Route::post('$plural', [{$controllerClass}, 'store'])->name('$plural.store');\n";
            $routeDefinition .= "    Route::get('$plural/{singular}/edit', [{$controllerClass}, 'edit'])->name('$plural.edit');\n";
            $routeDefinition .= "    Route::put('$plural/{singular}', [{$controllerClass}, 'update'])->name('$plural.update');\n";
            $routeDefinition .= "    Route::delete('$plural/{singular}', [{$controllerClass}, 'destroy'])->name('$plural.destroy');\n";
            $routeDefinition .= "});\n";
            $routeDefinition .= "Route::resource('$plural', {$controllerClass})->except(['create', 'store', 'edit', 'update', 'destroy']);\n";

            return $routeDefinition;
        }

        return "Route::resource('$plural', {$controllerClass});";
    }
}
