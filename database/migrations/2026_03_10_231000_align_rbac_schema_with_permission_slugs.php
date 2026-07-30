<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureRolesSuperAdminColumn();
        $this->ensurePermissionsSlugAndGroupColumns();
        $this->backfillPermissionSlugAndGroup();
        $this->ensureAdminRoleSuperAdminFlag();
        $this->ensureAtLeastOneSuperAdminUser();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table): void {
                try {
                    $table->dropUnique('permissions_slug_unique');
                } catch (\Throwable $e) {
                    // ignore
                }

                if (Schema::hasColumn('permissions', 'slug')) {
                    $table->dropColumn('slug');
                }

                if (Schema::hasColumn('permissions', 'group')) {
                    $table->dropColumn('group');
                }
            });
        }

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'is_super_admin')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropColumn('is_super_admin');
            });
        }
    }

    private function ensureRolesSuperAdminColumn(): void
    {
        if (! Schema::hasTable('roles') || Schema::hasColumn('roles', 'is_super_admin')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('is_system');
            $table->index('is_super_admin', 'roles_is_super_admin_index');
        });
    }

    private function ensurePermissionsSlugAndGroupColumns(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Schema::table('permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('permissions', 'slug')) {
                $table->string('slug', 100)->nullable()->after('name');
            }

            if (! Schema::hasColumn('permissions', 'group')) {
                $table->string('group', 100)->nullable()->after('slug');
            }
        });
    }

    private function backfillPermissionSlugAndGroup(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')
            ->select(['id', 'slug', 'code', 'group', 'group_name'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $slug = strtolower(trim((string) ($row->slug ?? '')));
                    $code = strtolower(trim((string) ($row->code ?? '')));
                    $group = trim((string) ($row->group ?? ''));
                    $groupName = trim((string) ($row->group_name ?? ''));

                    DB::table('permissions')->where('id', $row->id)->update([
                        'slug' => $slug !== '' ? $slug : ($code !== '' ? $code : 'permission.'.$row->id),
                        'group' => $group !== '' ? $group : ($groupName !== '' ? $groupName : 'General'),
                    ]);
                }
            });

        try {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->unique('slug', 'permissions_slug_unique');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function ensureAdminRoleSuperAdminFlag(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'is_super_admin')) {
            return;
        }

        DB::table('roles')
            ->where('slug', User::ROLE_ADMIN)
            ->update(['is_super_admin' => 1]);
    }

    private function ensureAtLeastOneSuperAdminUser(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_super_admin')) {
            return;
        }

        if (DB::table('users')->where('is_super_admin', 1)->exists()) {
            return;
        }

        $safeUserId = DB::table('users')->where('id', 1)->value('id');
        if ($safeUserId !== null) {
            DB::table('users')->where('id', $safeUserId)->update([
                'role' => User::ROLE_ADMIN,
                'is_super_admin' => 1,
            ]);

            return;
        }

        $adminUserId = DB::table('users')->where('role', User::ROLE_ADMIN)->orderBy('id')->value('id');
        if ($adminUserId !== null) {
            DB::table('users')->where('id', $adminUserId)->update(['is_super_admin' => 1]);

            return;
        }

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId !== null) {
            DB::table('users')->where('id', $firstUserId)->update([
                'role' => User::ROLE_ADMIN,
                'is_super_admin' => 1,
            ]);
        }
    }
};
