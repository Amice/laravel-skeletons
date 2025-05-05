<?php

namespace App\Services\Views;

use Illuminate\Support\Facades\File;

class LayoutViewGenerator extends BaseViewGenerator
{
    /**
     * Generate layout views from stubs.
     */
    public function generate(): ?array
    {
        // Define paths
        $layoutsPath = $this->getPath(resource_path("views/layouts"));
        $stubsPath = $this->getPath(resource_path("stubs/views/layouts"));
        if ($this->withBootStrap) {
            $stubsPath = $this->getPath(resource_path("stubs/views/layouts/bootstrap"));
        }

        // Ensure the layouts directory exists
        File::ensureDirectoryExists($layoutsPath);

        // Get all .stub files from the stubs/layouts directory
        $stubFiles = File::glob($stubsPath . DIRECTORY_SEPARATOR . "*.stub");

        foreach ($stubFiles as $stubFile) {
            // Get the destination file path
            $fileName = basename($stubFile); // Extract file name from path
            $destinationFile = $layoutsPath . DIRECTORY_SEPARATOR . str_replace("stub", "blade.php", $fileName);

            // Check if the destination file already exists
            if (!File::exists($destinationFile)) {
                File::copy($stubFile, $destinationFile); // Copy the stub file to the destination
                $this->generatedFiles[] = $destinationFile; // Track the generated file
                $this->command->info("✅ Layout created: {$destinationFile}");
            }
        }

        return [
            'generated_files' => $this->generatedFiles,
            'backup_files'    => $this->backupFiles,
        ];
    }
}
