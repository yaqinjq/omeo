<?php

namespace App\Support;

class ImportHeaderMatchResult
{
    /**
     * @param array<int,string> $rawHeaders
     * @param array<int,string> $canonicalHeaders
     * @param array<string,int> $indexes
     * @param array<int,string> $unknownHeaders
     * @param array<int,string> $missingRequired
     */
    public function __construct(
        public readonly array $rawHeaders,
        public readonly array $canonicalHeaders,
        public readonly array $indexes,
        public readonly array $unknownHeaders,
        public readonly array $missingRequired,
    ) {
    }

    public function isValid(): bool
    {
        return $this->missingRequired === [];
    }

    /**
     * @param array<int,mixed> $rawRow
     * @return array<string,string>
     */
    public function mapRow(array $rawRow): array
    {
        $row = [];

        foreach ($this->canonicalHeaders as $header) {
            $row[$header] = '';
        }

        foreach ($this->indexes as $header => $index) {
            $row[$header] = trim((string) ($rawRow[$index] ?? ''));
        }

        return $row;
    }

    /**
     * @return array<int,string>
     */
    public function warnings(): array
    {
        $warnings = [];

        if ($this->unknownHeaders !== []) {
            $warnings[] = 'Kolom tambahan diabaikan: ' . implode(', ', $this->unknownHeaders) . '.';
        }

        return $warnings;
    }
}
