<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class IqTemplateExport implements FromArray, WithHeadings
{
    /**
     * @param array<int,string> $headers
     * @param array<int,array<int,string|int|null>> $rows
     */
    public function __construct(
        private readonly array $headers,
        private readonly array $rows,
    ) {
    }

    /**
     * @return array<int,string>
     */
    public function headings(): array
    {
        return $this->headers;
    }

    /**
     * @return array<int,array<int,string|int|null>>
     */
    public function array(): array
    {
        return $this->rows;
    }
}
