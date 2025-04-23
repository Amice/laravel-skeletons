<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateViewUpdater extends AbstractViewUpdater
{

    protected string $viewType = 'create';

    public function updateViewContent()
    {
        $needle = '<!-- form-input-fields -->';
        if (!Str::contains($this->viewContent, $needle)) {
            $this->command->info(ucfirst($this->viewType) . " view no changes.");
            return;
        }

        $formFields = $this->generateViewContent();

        // Inject form fields into the Blade template
        $this->viewContent = str_replace($needle, $formFields, $this->viewContent);

        $this->saveView();
    }

    /**
     * Dynamically generates form fields based on column data types.
     */
    protected function generateViewContent(): string
    {
        $fields = "\n";

        foreach ($this->columns as $column) {
            $fieldName = $column->Field;
            // Skip primary key field
            if ($fieldName === $this->primaryKey) {
                continue;
            }
            $inputType = $this->getColumnType($column);
            $required = ($column->Null === 'NO') ? 'required' : '';
            $oldValue = "{{ old('$fieldName') }}"; // Retains input after validation
            $fields .= "\n<fieldset>\n";
            if ($inputType === 'select') {
                $options = $this->extractEnumOptions($column->Type);

                $fields .=
                    <<<BLADE
                        <label for="$fieldName">{{ __('messages.$fieldName') }}</label>
                        <select name="$fieldName" id="$fieldName" $required>
                            @foreach($options as \$option)
                                <option value="{{ \$option }}" {{ \$option == $oldValue ? 'selected' : '' }}>{{ \$option }}</option>
                            @endforeach
                        </select>
                    BLADE;
            } elseif ($inputType === 'checkbox') {
                $checked = "{{ old('$fieldName') ? 'checked' : '' }}";

                $fields .=
                    <<<BLADE
                        <label for="$fieldName">{{ __('messages.$fieldName') }}</label>
                        <input type="checkbox" name="$fieldName" id="$fieldName" value="1" $checked>
                    BLADE;
            } else {
                $fields .=
                    <<<BLADE
                        <label for="$fieldName">{{ __('messages.$fieldName') }}</label>
                        <input type="$inputType" name="$fieldName" id="$fieldName" $required placeholder="{{ __('messages.$fieldName') }}" value="$oldValue">
                    BLADE;
            }
            $fields .= "\n</fieldset>\n";
        }

        return $fields;
    }

}
