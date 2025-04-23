<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EditViewUpdater extends AbstractViewUpdater
{

    protected string $viewType = 'edit';

    public function updateViewContent()
    {
        $needle = '<!-- form-input-fields -->';
        if (!Str::contains($this->viewContent, $needle)) {
            $this->command->info(ucfirst($this->viewType) . " view no changes.");
            return;
        }

        $formFields = $this->generateViewContent();

        $this->viewContent = str_replace('<!-- form-input-fields -->', $formFields, $this->viewContent);

        $this->saveView();
    }

    protected function generateViewContent(): array|string
    {
        $singular = Str::lower($this->model);
        $fields = "\n";

        foreach ($this->columns as $column) {
            $fieldName = $column->Field;
            if ($fieldName === $this->primaryKey) {
                continue;
            }
            $inputType = $this->getColumnType($column);
            $required = ($column->Null === 'NO') ? 'required' : '';
            $oldValue = "{{ $$singular->$fieldName ?? old('$fieldName') }}"; // Use existing data or fallback to old input

            $fields .= "\n<fieldset>\n";

            // Handle SELECT (ENUM) fields
            if ($inputType === 'select') {
                $options = $this->extractEnumOptions($column->Type);

                $fields .= <<<BLADE
                <label for="$fieldName">{{ __('messages.$fieldName') }}</label>
                <select name="$fieldName" id="$fieldName" $required>
                    @foreach($options as \$option)
                        <option value="{{ \$option }}" {{ \$option == $oldValue ? 'selected' : '' }}>{{ \$option }}</option>
                    @endforeach
                </select>
            BLADE;
            } // Handle CHECKBOX fields
            elseif ($inputType === 'checkbox') {
                $checked = "{{ $$singular->$fieldName ? 'checked' : '' }}";
                $value = $checked ? 1 : 0;

                $fields .= <<<BLADE
                <label for="$fieldName">{{ __('messages.$fieldName') }}</label>
                <input type="checkbox" name="$fieldName" id="$fieldName" value="$value" $checked>
            BLADE;
            } // Handle standard input fields
            else {
                $fields .= <<<BLADE
                <label for="$fieldName">{{ __('messages.$fieldName') }}</label>
                <input type="$inputType" name="$fieldName" id="$fieldName" $required placeholder="{{ __('messages.$fieldName') }}" value="$oldValue">
            BLADE;
            }

            $fields .= "\n</fieldset>\n";
        }

        return $fields;
    }

}
