<?php
namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MenuGenerator extends AbstractGenerator
{
    protected $addedItems = [];


    public function generate(): ?array
    {
        $routeName = $this->tableName;
        $filePath = $this->getPath(resource_path('views/layouts/nav.blade.php'));
        if (!File::exists($filePath)) {
            $this->command->error("File not found: {$filePath}");
            return null;
        }

        // Read the current content of nav.blade.php
        $content = File::get($filePath);

        // Prepare the menu item code snippet
        $menuItem = "<li><a href=\"{{ route('{$routeName}.index') }}\">{{ __('{$routeName}.{$routeName}') }}</a></li>";
        $this->addedItems[$this->modelName] = $menuItem;

        // Check if the menu item already exists
        if (Str::contains($content, $menuItem)) {
            $this->command->info("Menu item for {$this->modelName} already exists, skipping insertion.");
            return null;
        }

        // Insert the new menu item just before the closing </ul> tag
        if(Str::contains($content, '</ul>')) {
            $content = Str::replaceLast('</ul>', "    {$menuItem}\n</ul>", $content);
            File::put($filePath, $content);
            $this->command->info("Menu item for {$this->modelName} added successfully.");
        } else {
            $this->command->error("No <ul> tag found in nav.blade.php. Cannot add menu item.");
        }

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }

    /**
     * Rolls back changes to layouts/nav.blade.php.
     */
    public function rollback(): void
    {
        $filePath = $this->getPath(resource_path('views/layouts/nav.blade.php'));

        // Ensure nav.blade.php exists
        if (!File::exists($filePath)) {
            $this->command->error("nav.blade.php not found. Cannot perform rollback.");
            return;
        }

        // Read the current content of nav.blade.php
        $content = File::get($filePath);

        // Build the menu item to remove
        foreach ($this->addedItems as $modelName => $menuItem) {
            // Remove the menu item from the content
            if (Str::contains($content, $menuItem)) {
                $content = Str::replaceFirst("    {$menuItem}\n", '', $content); // Ensure proper indentation handling
                File::put($filePath, $content);
                $this->command->info("Menu item for {$modelName} successfully removed from nav.blade.php.");
            } else {
                $this->command->warn("Menu item for {$modelName} not found in nav.blade.php. Rollback skipped.");
            }
        }
    }
}
