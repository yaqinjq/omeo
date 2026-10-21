<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Role baru "Koordinator" (OMEO Sport) — orang yang ditunjuk mengelola
 * event cabang olahraga tertentu. Idempotent (aman dijalankan ulang / di
 * fresh install) mengikuti pola 2026_06_11_000001_create_finance_role_and_permissions.php
 * setelah bug duplikatnya diperbaiki.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $roleId = DB::table('roles')->where('slug', 'koordinator')->value('id');
        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'slug'           => 'koordinator',
                'name'           => 'Koordinator Sport',
                'description'    => 'Orang yang ditunjuk membuat & mengelola event OMEO Sport yang menjadi tanggung jawabnya',
                'is_system'      => false,
                'is_super_admin' => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $now = now();
        $permissions = [
            ['code' => 'sport.events.manage', 'name' => 'Kelola Event Sport (milik sendiri)', 'group' => 'Sport'],
            ['code' => 'sport.cancellations.decide', 'name' => 'Approve/Reject Pembatalan Peserta', 'group' => 'Sport'],
            ['code' => 'sport.roster.view', 'name' => 'Lihat Roster & Kehadiran Event', 'group' => 'Sport'],
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $existingId = DB::table('permissions')->where('code', $perm['code'])->value('id');
            $permissionIds[] = $existingId ?: DB::table('permissions')->insertGetId([
                'code' => $perm['code'],
                'slug' => $perm['code'],
                'name' => $perm['name'],
                'group_name' => $perm['group'],
                'group' => $perm['group'],
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($permissionIds as $permId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Admin (super admin) selalu dapat semua permission baru juga, mengikuti pola migration lain.
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($adminRoleId) {
            foreach ($permissionIds as $permId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Admin Finance (role 'finance') dapat permission view/report Sport — ditambahkan di migration berikutnya
        // bareng tabel reward/report supaya scope-nya jelas, bukan di sini.
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('slug', 'koordinator')->first();
        if ($role) {
            DB::table('permission_role')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }

        DB::table('permissions')->whereIn('code', [
            'sport.events.manage', 'sport.cancellations.decide', 'sport.roster.view',
        ])->delete();
    }
};
