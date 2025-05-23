<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RouteGenerator extends AbstractGenerator
{

    private bool $withAuth = false;

    public function __construct($command, array $parsedData, $isApi, $withAuth = false)
    {
        parent::__construct($command, $parsedData, $isApi);
        $this->withAuth = $withAuth;
    }

    public function generate(): ?array
    {
        $stubFileName = $this->getRouteStubFileName();
        try {
            $stubContent = self::getStubContent($stubFileName);
        }
        catch (\Exception $e) {
            $this->command->error($e->getMessage());
            return null;
        }
        $controller = "{$this->modelName}Controller";

        $placeholders = [
            '{{ controller }}' => $controller,
        ];
        $content = $this->replacePlaceholders($stubContent, $placeholders);
        $destFileName = "routes/web/{$this->tableName}.php";
        if ($this->isApi) {
            $destFileName = "routes/api/{$this->tableName}.php";
        }
        $filePath = self::getPath(base_path($destFileName));
        File::ensureDirectoryExists(dirname($filePath));
        $this->createBackup($filePath);
        File::put($filePath, $content);
        $this->generatedFiles[] = $filePath;
        if ($this->updateRoutes()) {
            $routeType = $this->isApi ? 'API' : 'Web';
            $this->command->info("✅ {$routeType} routes for {$this->modelName} created successfully in routes" . DIRECTORY_SEPARATOR . "{$this->tableName}.php");
        }

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }

    public function updateRoutes(): bool
    {
        $routesFileName = $this->isApi ? "api.php" : "web.php";
        $routesFilePath = self::getPath(base_path("routes/$routesFileName"));
        if (!File::exists($routesFilePath)) {
            $this->command->error("❗The routes/$routesFileName file is missing. This is required when generating routes.");
            $this->command->line('💡 Please ensure this file exists in your routes directory.');
            if ($this->isApi) {
                $this->command->line('   You might need to create it manually or ensure your Laravel installation supports API routing.');
                $this->command->line('   You might run the following command: <fg=yellow>php artisan install:api</>');
                $this->lcommand->ine('   Alternatively, ensure your Laravel installation includes API routing setup (e.g., by using the Laravel Breeze or Jetstream with API option during installation).');
            } else {
                $this->command->line('   A basic web.php file should exist by default in a standard Laravel installation.');
            }
            $this->command->warn("❗ Skipping route inclusion in $routesFileName.");
            $this->command->error('❌ Aborting the process.');
            return false;
        }
        // Read the existing web.php content
        $webContent = File::exists($routesFilePath) ? File::get($routesFilePath) : "<?php\n\n";

        // Add require_once statements for only the files generated by this class
        foreach ($this->generatedFiles as $filePath) {
            $relativePath = Str::replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
            $requireLine = "require_once base_path('{$relativePath}');";

            if (!Str::contains($webContent, $requireLine)) {
                $webContent .= $requireLine . "\n";
            }
        }

        // Write the updated content back to web.php
        File::put($routesFilePath, $webContent);
        $this->command->info("$routesFileName updated with route file imports.");

        return true;
    }

    public function rollback(): void
    {
        foreach ($this->generatedFiles as $filePath) {
            if (File::exists($filePath)) {
                self::removeRequireFromRoutes($filePath, $this->isApi);
            }
        }
        parent::rollback();
    }

    public static function removeRequireFromRoutes(string $filePath, $isApi = false)
    {
        $routesFileName = $isApi ? "api.php" : "web.php";
        $routesFilePath = self::getPath(base_path("routes/$routesFileName"));

        // Check if web.php exists
        if (!File::exists($routesFilePath)) {
            echo "\n❗$routesFileName not found. Skipping removal of require instruction.";
            return;
        }

        // Read the content of web.php
        $webContent = File::get($routesFilePath);

        // Generate the require_once line to remove
        $relativePath = Str::replace(base_path() . DIRECTORY_SEPARATOR, '', $filePath);
        $requireLine = "require_once base_path('$relativePath');";

        // Remove the line if it exists
        if (Str::contains($webContent, $requireLine)) {
            $webContent = Str::replace($requireLine . "\n", '', $webContent);
            File::put($routesFilePath, $webContent);
            echo "Removed require instruction for $relativePath from $routesFileName.\n";
        }
    }

    protected function getRouteStubFileName(): string
    {
        $baseName = 'routes';
        $pathPrefix = '';

        if ($this->isApi) {
            $pathPrefix = 'api/';
            $baseName = 'routes'; // Or 'api_routes' if you prefer
        }

        if ($this->withAuth) {
            $baseName .= '_auth';
        }

        return self::stub_path($pathPrefix . $baseName . '.stub');
    }
}
