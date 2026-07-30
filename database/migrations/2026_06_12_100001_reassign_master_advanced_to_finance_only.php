<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $masterPerms = [
            'master.company_groups.manage',
            'master.legal_entities.manage',
            'master.regions.manage',
            'master.positions.manage',
        ];

        $permIds = DB::table('permissions')
            ->whereIn('code', $masterPerms)
            ->pluck('id');

        if ($permIds->isEmpty()) return;

        // 1. Hapus dari role HRD
        $hrdRole = DB::table('roles')->where('slug', 'hrd')->first();
        if ($hrdRole) {
            DB::table('permission_role')
                ->where('role_id', $hrdRole->id)
                ->whereIn('permission_id', $permIds)
                ->delete();
        }

        // 2. Hapus dari role manager (kalau ada)
        $managerRole = DB::table('roles')->where('slug', 'manager')->first();
        if ($managerRole) {
            DB::table('permission_role')
                ->where('role_id', $managerRole->id)
                ->whereIn('permission_id', $permIds)
                ->delete();
        }

        // 3. Pastikan finance role punya SEMUA permission ini
        $financeRole = DB::table('roles')->where('slug', 'finance')->first();
        $now = now();
        if ($financeRole) {
            foreach ($permIds as $permId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id'       => $financeRole->id,
                    'permission_id' => $permId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $masterPerms = [
            'master.company_groups.manage',
            'master.legal_entities.manage',
            'master.regions.manage',
            'master.positions.manage',
        ];
        $permIds = DB::table('permissions')
            ->whereIn('code', $masterPerms)
            ->pluck('id');

        $hrdRole = DB::table('roles')->where('slug', 'hrd')->first();
        $now = now();
        if ($hrdRole) {
            foreach ($permIds as $permId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id'       => $hrdRole->id,
                    'permission_id' => $permId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }
};
