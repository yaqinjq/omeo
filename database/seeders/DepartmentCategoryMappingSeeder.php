<?php

namespace Database\Seeders;

use App\Models\DepartmentCategoryMapping;
use Illuminate\Database\Seeder;

class DepartmentCategoryMappingSeeder extends Seeder
{
    public function run(): void
    {
        $subDepts = [
            'BAR'                       => 'bar',
            'KITCHEN OUTLET'            => 'kitchen',
            'KITCHEN PRODUCTION'        => 'kitchen',
            'SERVICE'                   => 'service',
            'FINANCE AND ACCOUNTING'    => 'office',
            'HUMAN RESOURCE DEPARTMENT' => 'office',
            'IT'                        => 'office',
            'MAINTENANCE'               => 'office',
            'MANAGEMENT'                => 'office',
            'PURCHASING'                => 'office',
            'DESIGN MARKETING'          => 'office',
            'OFFICE PRODUCTION'         => 'office',
        ];

        foreach ($subDepts as $value => $category) {
            DepartmentCategoryMapping::firstOrCreate(
                ['match_type' => 'sub_dept', 'match_value' => $value],
                ['category' => $category, 'is_active' => true]
            );
        }

        $positions = [
            'MANAGING DIRECTOR' => 'prive',
            'WAKIL DIREKTUR 1'  => 'prive',
            'WAKIL DIREKTUR 2'  => 'prive',
            'DIREKTUR'          => 'prive',
        ];

        foreach ($positions as $value => $category) {
            DepartmentCategoryMapping::firstOrCreate(
                ['match_type' => 'position', 'match_value' => $value],
                ['category' => $category, 'is_active' => true]
            );
        }

        $this->command->info('DepartmentCategoryMapping seeded: ' . DepartmentCategoryMapping::count() . ' rows.');
    }
}
