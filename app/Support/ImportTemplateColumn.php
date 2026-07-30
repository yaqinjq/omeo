<?php

namespace App\Support;

class ImportTemplateColumn
{
    /**
     * @param array<int,string> $aliases
     * @param array<string,string> $labels
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $required = false,
        public readonly array $aliases = [],
        public readonly array $labels = [],
    ) {
    }

    /**
     * @param array<int,string> $aliases
     * @param array<string,string> $labels
     */
    public static function make(
        string $name,
        bool $required = false,
        array $aliases = [],
        array $labels = [],
    ): self {
        return new self($name, $required, $aliases, $labels);
    }

    public function exportLabel(string $variant = 'canonical'): string
    {
        return $this->labels[$variant] ?? $this->labels['export'] ?? $this->name;
    }
}
