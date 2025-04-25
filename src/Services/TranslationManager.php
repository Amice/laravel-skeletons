<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TranslationManager
{
    use ModelDataTrait;

    protected $command;

    public function __construct(string $model, $command)
    {
        $this->command = $command;
        $this->initializeModelData($model);
    }

    public function generateTranslationFiles(): void
    {
        $langDirectories = File::directories(resource_path('lang'));

        foreach ($langDirectories as $langDir) {
            $languageCode = basename($langDir);
            $langFile = $langDir . DIRECTORY_SEPARATOR . Str::snake($this->model) . ".php";

            // Initialize translations from fields
            $newTranslations = [];
            foreach ($this->fillableFields as $field) {
                $newTranslations[$field] = ucfirst(str_replace('_', ' ', $field));
            }

            // Check if translation file exists
            if (File::exists($langFile)) {
                // Parse existing translations
                $existingTranslations = include $langFile;

                // Merge existing with new translations
                $mergedTranslations = array_merge($existingTranslations, $newTranslations);

                // Create a backup before overwriting
                $backupFile = $langFile . '.bak';
                File::copy($langFile, $backupFile);
                $this->command->info("Backup created: {$backupFile}");
            } else {
                $mergedTranslations = $newTranslations;
            }

            // Convert merged translations into a PHP file
            $translationContent = "<?php\n\nreturn " . var_export($mergedTranslations, true) . ";\n";

            // Save the merged translations
            File::put($langFile, $translationContent);

            $this->command->info("✅ Translation file created/updated for language [{$languageCode}]: {$langFile}");
        }
    }

    public function copyLocalizationFiles(string $basePath)
    {
        $sourcePath = $basePath . 'resources' . DIRECTORY_SEPARATOR . 'lang';
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
                    $this->command->info("✅ Language files have been created successfully! ($destinationFile)");
                }
            }
        }
    }

    public static function deleteLanguageFiles(string $model, $command, bool $purge = false): void
    {
        $langDirectories = File::directories(resource_path('lang'));

        $bakFiles = [];
        foreach ($langDirectories as $langDir) {
            $languageCode = basename($langDir);
            $langFile = $langDir . DIRECTORY_SEPARATOR . Str::snake($model) . ".php";
            $bakFiles[] = $langFile . '.bak';
            if (File::exists($langFile)) {
                File::delete($langFile);
                $command->info("❌ Deleted language file: $langFile");
            }

            $langSkeletonsPath = resource_path("lang" . DIRECTORY_SEPARATOR . $languageCode . DIRECTORY_SEPARATOR . "skeletons.php");
            $bakFiles[] = $langSkeletonsPath . '.bak';
            if (File::exists($langSkeletonsPath)) {
                File::delete($langSkeletonsPath);
                $command->info("❌ Deleted language file: $langSkeletonsPath");
            }
        }
        if ($purge) {
            foreach ($bakFiles as $bakFile) {
                if (File::exists($bakFile)) {
                    File::delete($bakFile);
                    $command->info("❌ Deleted: {$bakFile}");
                }
            }
        }
    }
}
