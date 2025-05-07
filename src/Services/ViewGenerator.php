<?php

namespace KovacsLaci\LaravelSkeletons\Services;

use KovacsLaci\LaravelSkeletons\Services\Views\BaseViewGenerator;
use KovacsLaci\LaravelSkeletons\Services\Views\CreateViewGenerator;
use KovacsLaci\LaravelSkeletons\Services\Views\EditViewGenerator;
use KovacsLaci\LaravelSkeletons\Services\Views\IndexViewGenerator;
use KovacsLaci\LaravelSkeletons\Services\Views\LayoutViewGenerator;
use KovacsLaci\LaravelSkeletons\Services\Views\ShowViewGenerator;

class ViewGenerator extends BaseViewGenerator
{
    private IndexViewGenerator $indexViewGenerator;
    private CreateViewGenerator $createViewGenerator;
    private EditViewGenerator $editViewGenerator;
    private ShowViewGenerator $showViewGenerator;
    private LayoutViewGenerator $layoutViewGenerator;

    public function __construct($command, $parsedData, $cssStyle, $withAuth = false)
    {
        parent::__construct($command, $parsedData, $cssStyle);
        $this->indexViewGenerator = new IndexViewGenerator($command, $parsedData, $cssStyle, $withAuth);
        $this->createViewGenerator = new CreateViewGenerator($command, $parsedData, $cssStyle);
        $this->editViewGenerator = new EditViewGenerator($command, $parsedData, $cssStyle);
        $this->showViewGenerator = new ShowViewGenerator($command, $parsedData, $cssStyle);
        $this->layoutViewGenerator = new LayoutViewGenerator($command, $parsedData, $cssStyle);
    }

    public function generate(): ?array
    {
        $allGeneratedFiles = [
            'generated_files' => [],
            'backup_files'    => [],
        ];
        $result = $this->indexViewGenerator->generate();
        if (!empty($result)) {
            $allGeneratedFiles['generated_files'] = array_merge($allGeneratedFiles['generated_files'], $result['generated_files']);
            $allGeneratedFiles['backup_files']    = array_merge($allGeneratedFiles['backup_files'],    $result['backup_files']);
        }
        $result = $this->createViewGenerator->generate();
        if (!empty($result)) {
            $allGeneratedFiles['generated_files'] = array_merge($allGeneratedFiles['generated_files'], $result['generated_files']);
            $allGeneratedFiles['backup_files']    = array_merge($allGeneratedFiles['backup_files'],    $result['backup_files']);
        }
        $result = $this->editViewGenerator->generate();
        if (!empty($result)) {
            $allGeneratedFiles['generated_files'] = array_merge($allGeneratedFiles['generated_files'], $result['generated_files']);
            $allGeneratedFiles['backup_files']    = array_merge($allGeneratedFiles['backup_files'],    $result['backup_files']);
        }
        $result = $this->showViewGenerator->generate();
        if (!empty($result)) {
            $allGeneratedFiles['generated_files'] = array_merge($allGeneratedFiles['generated_files'], $result['generated_files']);
            $allGeneratedFiles['backup_files']    = array_merge($allGeneratedFiles['backup_files'],    $result['backup_files']);
        }
        $result = $this->layoutViewGenerator->generate();
        if (!empty($result)) {
            $allGeneratedFiles['generated_files'] = array_merge($allGeneratedFiles['generated_files'], $result['generated_files']);
            $allGeneratedFiles['backup_files']    = array_merge($allGeneratedFiles['backup_files'],    $result['backup_files']);
        }

        return $allGeneratedFiles;
    }

    public function rollback(): void
    {
        $this->indexViewGenerator->rollback();
        $this->createViewGenerator->rollback();
        $this->editViewGenerator->rollback();
        $this->showViewGenerator->rollback();
        $this->layoutViewGenerator->rollback();
    }
}
