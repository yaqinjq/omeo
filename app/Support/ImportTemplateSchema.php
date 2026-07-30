<?php

namespace App\Support;

class ImportTemplateSchema
{
    /** @var array<int,ImportTemplateColumn> */
    private array $columns;

    /**
     * @param array<int,ImportTemplateColumn> $columns
     */
    public function __construct(array $columns)
    {
        $this->columns = array_values($columns);
    }

    /**
     * @param array<int,ImportTemplateColumn> $columns
     */
    public static function make(array $columns): self
    {
        return new self($columns);
    }

    /**
     * @return array<int,ImportTemplateColumn>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int,string>
     */
    public function columnNames(): array
    {
        return array_map(static fn (ImportTemplateColumn $column) => $column->name, $this->columns);
    }

    /**
     * @return array<int,string>
     */
    public function requiredColumns(): array
    {
        return array_values(array_map(
            static fn (ImportTemplateColumn $column) => $column->name,
            array_filter($this->columns, static fn (ImportTemplateColumn $column) => $column->required)
        ));
    }

    /**
     * @return array<int,string>
     */
    public function exportHeaders(string $variant = 'canonical'): array
    {
        return array_map(
            static fn (ImportTemplateColumn $column) => $column->exportLabel($variant),
            $this->columns
        );
    }

    /**
     * @return array<string,string>
     */
    public function aliases(): array
    {
        $aliases = [];

        foreach ($this->columns as $column) {
            $aliases[HeaderNormalizer::normalize($column->name)] = $column->name;

            foreach ($column->aliases as $alias) {
                $aliases[HeaderNormalizer::normalize($alias)] = $column->name;
            }

            foreach ($column->labels as $label) {
                $aliases[HeaderNormalizer::normalize($label)] = $column->name;
            }
        }

        return $aliases;
    }
}
