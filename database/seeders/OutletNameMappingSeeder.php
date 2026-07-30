<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\OutletNameMapping;
use Illuminate\Database\Seeder;

class OutletNameMappingSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            'AH PEK KOPITIAM BENHILL'           => 'AH PEK KOPITIAM BENDUNGAN HILIR',
            'AH PEK KOPITIAM BENDUNGAN HILIR'   => 'AH PEK KOPITIAM BENDUNGAN HILIR',
            'AH PEK KOPITIAM BINTARO'           => 'AH PEK KOPITIAM BINTARO',
            'AH PEK KOPITIAM GRESSMALL'         => 'AH PEK GRESS MALL',
            'AH PEK KOPITIAM GRESS MALL'        => 'AH PEK GRESS MALL',
            'AH PEK KOPITIAM KUPANG INDAH'      => 'AH PEK KUPANG INDAH',
            'AH PEK KOPITIAM MARGONDA'          => 'AH PEK KOPITIAM MARGONDA',
            'AH PEK KOPITIAM MATARAM'           => 'AH PEK KOPITIAM MATARAM',
            'AH PEK KOPITIAM MERR'              => 'AH PEK KOPITIAM MERR 221',
            'AH PEK KOPITIAM PAKUWON CITY MALL' => 'AH PEK KOPITIAM PAKUWON CITY MALL (PCM)',
            'AH PEK KOPITIAM PCM'               => 'AH PEK KOPITIAM PAKUWON CITY MALL (PCM)',
            'AH PEK KOPITIAM SAMARINDA'         => 'AHPEK KOPITIAM SAMARINDA',
            'AH PEK KOPITIAM TANJUNG DUREN'     => 'AH PEK TANJUNG DUREN',
            'AH PEK KOPITIAM TUNJUNGAN'         => 'AH PEK TUNJUNGAN',
            'AH PEK KOPITIAM MALANG'            => 'AH PEK SEMERU',
            'TOKIO-O! MANDALA MALANG'           => 'TOKIO O JAPANASE CAFE',
            'TOKIO O! MANDALA MALANG'           => 'TOKIO O JAPANASE CAFE',
        ];

        foreach ($mappings as $raw => $masterName) {
            $outlet = Outlet::whereRaw('UPPER(name) = ?', [strtoupper($masterName)])->first();
            if (! $outlet) {
                $this->command->warn("WARNING: Master outlet tidak ditemukan: {$masterName}");
                continue;
            }
            OutletNameMapping::firstOrCreate(
                ['raw_name' => $raw],
                ['outlet_id' => $outlet->id, 'is_active' => true]
            );
        }

        $this->command->info('OutletNameMapping seeded: ' . OutletNameMapping::count() . ' rows.');
    }
}
