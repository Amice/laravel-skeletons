<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class IndexViewUpdater extends AbstractViewUpdater
{

    protected string $viewType = 'index';

    public function updateViewContent()
    {
        $needles = ['<!-- table-head -->', '<!-- table-columns -->'];
        if (!Str::contains($this->viewContent, $needles)) {
            $this->command->info(ucfirst($this->viewType) . " view no changes.");
            return;
        }

        $formFields = $this->generateViewContent();

        // Inject form fields into the Blade template
        $this->viewContent = str_replace('<!-- table-head -->', $formFields['thead'], $this->viewContent);
        $this->viewContent = str_replace('<!-- table-columns -->', $formFields['tbody'], $this->viewContent);

        $this->saveView();
    }

    protected function generateViewContent(): array | string
    {
        $singular = Str::lower($this->model);

        $tHead = "\n<tr>";
        $tableColumns = "\n<tr>";
        foreach ($this->columns as $column) {
            $fieldName = $column->Field;
            $tHead .= "\n<th>{{ __('$singular.$fieldName') }}</th>";
            $tableColumns .= "\n<td>{{ $$singular->$fieldName}}</td>";
        }
        $tHead .= "\n<th>{{ __('skeletons.actions') }}</th>\n</tr>";
        $tableColumns .= $this->getRowActions();
        $tableColumns .= "\n</tr>";

        return ['thead' => $tHead, 'tbody' => $tableColumns];

    }

    private function getRowActions()
    {
        $singular = Str::lower($this->model);
        $plural = $this->tableName;
        return <<<BLADE
        <td>
            <a href="{{ route('$plural.show', $$singular->{$this->primaryKey}) }}">{{ __('skeletons.show')}}</a>
            {{-- uncomment @if / @endif when authentication is required --}}
            {{-- @if(auth()->check()) --}}
                <a href="{{ route('$plural.edit', $$singular->{$this->primaryKey}) }}">{{ __('skeletons.edit') }}</a>
                <form action="{{ route('$plural.destroy', $$singular->{$this->primaryKey}) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit">{{ __('skeletons.delete') }}</button>
                </form>
            {{-- @endif --}}
        </td>
        BLADE;
    }
}
