<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ShowViewUpdater extends AbstractViewUpdater
{

    protected string $viewType = 'show';

    public function updateViewContent()
    {
        $needle = '<!-- display-data -->';
        if (!Str::contains($this->viewContent, $needle)) {
            $this->command->info(ucfirst($this->viewType) . " view no changes.");
            return;
        }

        $displayFields = $this->generateViewContent();
        $this->viewContent = str_replace('<!-- display-data -->', $displayFields, $this->viewContent);

        $this->saveView();
    }

    protected function generateViewContent(): array|string
    {
        $singular = Str::lower($this->model);
        $fields = "\n";

        foreach ($this->columns as $column) {
            $fieldName = $column->Field;

            $fields .=
                <<<BLADE
                    <p><strong>{{ __('messages.$fieldName') }}:</strong> {{ \${$singular}->$fieldName }}</p>
                BLADE;
        }

        return $fields;
    }

}
