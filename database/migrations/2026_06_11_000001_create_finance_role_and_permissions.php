<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'slug'           => 'finance',
            'name'           => 'Finance',
            'description'    => 'Staff Finance — akses modul BPJS & Payroll',
            'is_system'      => false,
            'is_super_admin' => false,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $permissions = [
            ['code' => 'finance.view',          'slug' => 'finance.view',          'name' => 'Lihat Finance Dashboard',   'group_name' => 'Finance', 'group' => 'Finance'],
            ['code' => 'finance.import',         'slug' => 'finance.import',         'name' => 'Import Data Payroll',        'group_name' => 'Finance', 'group' => 'Finance'],
            ['code' => 'finance.import.review',  'slug' => 'finance.import.review',  'name' => 'Review & Konfirmasi Import', 'group_name' => 'Finance', 'group' => 'Finance'],
            ['code' => 'finance.bpjs.view',      'slug' => 'finance.bpjs.view',      'name' => 'Lihat Data BPJS',            'group_name' => 'Finance', 'group' => 'Finance'],
            ['code' => 'finance.bpjs.manage',    'slug' => 'finance.bpjs.manage',    'name' => 'Kelola Status Bayar BPJS',   'group_name' => 'Finance', 'group' => 'Finance'],
            ['code' => 'finance.export',         'slug' => 'finance.export',         'name' => 'Export Laporan Finance',     'group_name' => 'Finance', 'group' => 'Finance'],
        ];

        $now = now();
        $permissionIds = [];
        foreach ($permissions as $perm) {
            $permissionIds[] = DB::table('permissions')->insertGetId(array_merge($perm, [
                'description' => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]));
        }

        foreach ($permissionIds as $permId) {
            DB::table('permission_role')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        if ($adminRole) {
            foreach ($permissionIds as $permId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id'       => $adminRole->id,
                    'permission_id' => $permId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('slug', 'finance')->first();
        if ($role) {
            DB::table('permission_role')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }
        DB::table('permissions')->whereIn('code', [
            'finance.view', 'finance.import', 'finance.import.review',
            'finance.bpjs.view', 'finance.bpjs.manage', 'finance.export',
        ])->delete();
    }
};
