<?php

namespace KovacsLaci\LaravelSkeletons\Services\Views;

use KovacsLaci\LaravelSkeletons\Services\AbstractGenerator;
use Illuminate\Support\Str;

abstract class BaseViewGenerator extends AbstractGenerator
{
    protected bool $withBootStrap;

    public function __construct($command, array $parsedData, $withBootStrap = false)
    {
        parent::__construct($command, $parsedData);
        $this->withBootStrap = $withBootStrap;
    }
    abstract public function generate(): ?array;

    protected function getInputType(string $type): string
    {
        // Map database column types to HTML input types
        if (Str::contains($type, 'int')) {
            return 'number';
        }
        if (Str::contains($type, 'date')) {
            return 'date';
        }
        return 'text'; // Default input type
    }

}
