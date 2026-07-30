<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class EmployeesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStrictNullComparison
{
    public function __construct(private readonly Collection $rows, private readonly array $headings = [])
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if ($this->headings !== []) {
            return $this->headings;
        }

        return [
            'NIK',
            'NOKOM',
            'Nama Lengkap',
            'Email Login',
            'Email Private',
            'No Telepon',
            'Status',
            'Join Date',
            'Masa Kerja (bulan)',
            'Departemen',
            'Jabatan',
            'Brand',
            'Outlet',
            'Gaji Saat Ini',
            'Bank Utama',
            'No Rekening Utama',
            'Atas Nama Rekening',
            'NPWP',
            'BPJS Kesehatan',
            'BPJS Ketenagakerjaan',
            'SIM',
            'Passport',
            'KK',
            'Payroll Verified At',
            'Pendidikan Terakhir',
            'Perubahan Profile Pending',
            'Perubahan Profile Disetujui Terakhir',
        ];
    }
}
