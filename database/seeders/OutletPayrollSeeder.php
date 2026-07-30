<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletPayrollSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = [
            ['name' => 'Production Penangs Surabaya',      'outlet_type' => 'production'],
            ['name' => 'Dimsum Production',                 'outlet_type' => 'production'],
            ['name' => 'Production Quali Surabaya',         'outlet_type' => 'production'],
            ['name' => 'Production Quali Surabaya 2',       'outlet_type' => 'production'],
            ['name' => 'Production Quali Surabaya 3',       'outlet_type' => 'production'],
            ['name' => 'Kreasi Kopi Jaya',                  'outlet_type' => 'payroll'],
            ['name' => 'Kreasi Kopi Tiam',                  'outlet_type' => 'payroll'],
            ['name' => 'Berjasa Kopi Jaya',                 'outlet_type' => 'payroll'],
            ['name' => 'Production Multirasa Kopi Jaya',    'outlet_type' => 'production'],
        ];

        foreach ($outlets as $data) {
            Outlet::firstOrCreate(
                ['name' => $data['name']],
                ['outlet_type' => $data['outlet_type'], 'is_active' => true, 'brand_name' => 'MEO', 'timezone' => 'Asia/Jakarta']
            );
        }

        echo 'Outlet payroll/production selesai ditambahkan.' . PHP_EOL;
        echo 'Total outlets: ' . Outlet::count() . PHP_EOL;
        echo 'Operational: ' . Outlet::where('outlet_type', 'operational')->count() . PHP_EOL;
        echo 'Payroll: ' . Outlet::where('outlet_type', 'payroll')->count() . PHP_EOL;
        echo 'Production: ' . Outlet::where('outlet_type', 'production')->count() . PHP_EOL;
    }
}
