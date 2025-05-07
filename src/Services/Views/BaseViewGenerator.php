<?php

namespace KovacsLaci\LaravelSkeletons\Services\Views;

use KovacsLaci\LaravelSkeletons\Services\AbstractGenerator;
use Illuminate\Support\Str;

abstract class BaseViewGenerator extends AbstractGenerator
{
    protected string $cssStyle;

    public function __construct($command, array $parsedData, $cssStyle = '')
    {
        parent::__construct($command, $parsedData);
        $this->cssStyle = $cssStyle;
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

    protected function getStubFilePath(string $stubFileName): string
    {
        return $this->cssStyle ? self::stub_path("views/{$this->cssStyle}/$stubFileName") : self::stub_path("views/$stubFileName");
    }

}
