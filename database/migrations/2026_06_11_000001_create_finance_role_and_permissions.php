<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // BUGFIX (ditemukan saat audit test suite, 2026-10-20): migration ini
        // dulu pakai insertGetId tanpa cek duplikat, padahal
        // 2026_03_10_230000_create_dynamic_rbac_tables.php sudah lebih dulu
        // mengisi role/permission yang sama dari config('rbac.permissions')
        // (yang sejak itu sudah mencakup semua slug finance.*). Di production
        // ini tidak pernah ketahuan karena deploy cuma jalankan migrate
        // incremental (baris ini sudah lama tercatat selesai di sana), tapi
        // migrate:fresh/RefreshDatabase (dipakai test suite) langsung gagal
        // dengan UNIQUE constraint violation. Diubah jadi idempotent
        // (updateOrInsert) — tidak mengubah apa pun di production yang sudah
        // pernah menjalankan migration ini, cuma memperbaiki fresh-install.
        $roleId = DB::table('roles')->where('slug', 'finance')->value('id');
        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'slug'           => 'finance',
                'name'           => 'Finance',
                'description'    => 'Staff Finance — akses modul BPJS & Payroll',
                'is_system'      => false,
                'is_super_admin' => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

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
            $existingId = DB::table('permissions')->where('code', $perm['code'])->value('id');
            $permissionIds[] = $existingId ?: DB::table('permissions')->insertGetId(array_merge($perm, [
                'description' => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]));
        }

        foreach ($permissionIds as $permId) {
            DB::table('permission_role')->insertOrIgnore([
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
