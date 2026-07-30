<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['code' => 'master.company_groups.manage', 'name' => 'Kelola Group Perusahaan',       'group_name' => 'Master Lanjutan', 'group' => 'Master Lanjutan'],
            ['code' => 'master.legal_entities.manage', 'name' => 'Kelola Entitas Legal (PT/CV)',   'group_name' => 'Master Lanjutan', 'group' => 'Master Lanjutan'],
            ['code' => 'master.regions.manage',        'name' => 'Kelola Regional & UMR',          'group_name' => 'Master Lanjutan', 'group' => 'Master Lanjutan'],
            ['code' => 'finance.bpjs.rates.manage',    'name' => 'Kelola Tarif BPJS per Regional', 'group_name' => 'Finance',         'group' => 'Finance'],
            ['code' => 'finance.bpjs.legal.manage',    'name' => 'Kelola Akun Legal BPJS',         'group_name' => 'Finance',         'group' => 'Finance'],
            ['code' => 'master.positions.manage',      'name' => 'Kelola Master Posisi/Jabatan',   'group_name' => 'Master Lanjutan', 'group' => 'Master Lanjutan'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $perm['code']],
                [
                    'slug'        => $perm['code'],
                    'name'        => $perm['name'],
                    'group_name'  => $perm['group_name'],
                    'group'       => $perm['group'],
                    'description' => null,
                    'updated_at'  => $now,
                    'created_at'  => $now,
                ]
            );
        }

        // Assign to roles: hrd gets master.*, finance gets finance.bpjs.*
        $hrdRoleId     = DB::table('roles')->where('slug', 'hrd')->value('id');
        $financeRoleId = DB::table('roles')->where('slug', 'finance')->value('id');

        $hrdCodes = ['master.company_groups.manage', 'master.legal_entities.manage',
                     'master.regions.manage', 'master.positions.manage'];
        $financeCodes = ['finance.bpjs.rates.manage', 'finance.bpjs.legal.manage'];

        $this->assignPermissionsToRole($hrdRoleId, $hrdCodes, $now);
        $this->assignPermissionsToRole($financeRoleId, $financeCodes, $now);
    }

    private function assignPermissionsToRole(?int $roleId, array $codes, $now): void
    {
        if (!$roleId) return;

        foreach ($codes as $code) {
            $permId = DB::table('permissions')->where('code', $code)->value('id');
            if (!$permId) continue;

            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permId],
                ['created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $codes = [
            'master.company_groups.manage',
            'master.legal_entities.manage',
            'master.regions.manage',
            'finance.bpjs.rates.manage',
            'finance.bpjs.legal.manage',
            'master.positions.manage',
        ];

        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
