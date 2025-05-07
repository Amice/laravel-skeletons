<?php

namespace KovacsLaci\LaravelSkeletons\Services\Views;

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
        $stubsPath = self::stub_path("views/layouts");
        if ($this->cssStyle) {
            $stubsPath = self::stub_path("views/layouts/{$this->cssStyle}");
        }

        // Ensure the layouts directory exists
        File::ensureDirectoryExists($layoutsPath);

        // Get all .stub files from the stubs/layouts directory
        $stubFiles = File::glob($stubsPath . DIRECTORY_SEPARATOR . "*.stub");

        foreach ($stubFiles as $stubFile) {
            // Get the destination file path
            $fileName = basename($stubFile); // Extract file name from path
            $destinationFile = $layoutsPath . DIRECTORY_SEPARATOR . str_replace("stub", "blade.php", $fileName);
            $content = '';
            if ($fileName === 'app.stub') {
                $stubContent = self::getStubContent($stubFile);
                $placeholders = [];
                $content = $this->replacePlaceholders($stubContent, $placeholders);
            }
            // Check if the destination file already exists
            if (!File::exists($destinationFile)) {
                if ($content) {
                    File::put($destinationFile, $content);
                } else {
                    File::copy($stubFile, $destinationFile);
                }
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
