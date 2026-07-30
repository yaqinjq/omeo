<?php

use App\Models\User;
use App\Support\Rbac\PermissionMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createRolesTable();
        $this->createPermissionsTable();
        $this->createPermissionRoleTable();
        $this->addSuperAdminColumn();

        $this->seedRoles();
        $this->seedPermissions();
        $this->syncRolePermissions();
        $this->ensureSuperAdminSafety();
    }

    public function down(): void
    {
        if (Schema::hasTable('permission_role')) {
            Schema::drop('permission_role');
        }

        if (Schema::hasTable('permissions')) {
            Schema::drop('permissions');
        }

        if (Schema::hasTable('roles')) {
            Schema::drop('roles');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('is_super_admin');
            });
        }
    }

    private function createRolesTable(): void
    {
        if (Schema::hasTable('roles')) {
            return;
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    private function createPermissionsTable(): void
    {
        if (Schema::hasTable('permissions')) {
            return;
        }

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 100);
            $table->string('group_name', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    private function createPermissionRoleTable(): void
    {
        if (Schema::hasTable('permission_role')) {
            return;
        }

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id'], 'permission_role_unique');
        });
    }

    private function addSuperAdminColumn(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'is_super_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('role');
            $table->index('is_super_admin', 'users_is_super_admin_index');
        });
    }

    private function seedRoles(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $system = (array) config('rbac.system_roles', []);
        $now = now();

        $rows = [];

        foreach ($system as $slug => $name) {
            $rows[] = [
                'slug' => strtolower(trim((string) $slug)),
                'name' => (string) $name,
                'description' => 'System role',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $distinctRoles = DB::table('users')
            ->select('role')
            ->whereNotNull('role')
            ->distinct()
            ->pluck('role')
            ->all();

        foreach ($distinctRoles as $role) {
            $slug = strtolower(trim((string) $role));
            if ($slug === '') {
                continue;
            }

            if (! preg_match('/^[a-z0-9._-]+$/', $slug)) {
                continue;
            }

            if (array_key_exists($slug, $system)) {
                continue;
            }

            $rows[] = [
                'slug' => $slug,
                'name' => ucwords(str_replace(['_', '-'], ' ', $slug)),
                'description' => 'Imported from existing users.role',
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        DB::table('roles')->upsert(
            $rows,
            ['slug'],
            ['name', 'description', 'is_system', 'updated_at']
        );
    }

    private function seedPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $meta = PermissionMap::permissionMeta();
        if ($meta === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($meta as $code => $item) {
            $code = strtolower(trim((string) $code));
            if ($code === '') {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'name' => (string) data_get($item, 'label', $code),
                'group_name' => (string) data_get($item, 'group', 'General'),
                'description' => (string) data_get($item, 'description', ''),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('permissions')->upsert(
            $rows,
            ['code'],
            ['name', 'group_name', 'description', 'updated_at']
        );
    }

    private function syncRolePermissions(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $roles = DB::table('roles')->select(['id', 'slug'])->get();
        $permissions = DB::table('permissions')->select(['id', 'code'])->get()->keyBy('code');
        $now = now();

        foreach ($roles as $role) {
            $defaults = PermissionMap::defaultPermissionsForRole((string) $role->slug);
            if ($defaults === []) {
                continue;
            }

            $attachRows = [];

            if (in_array('*', $defaults, true)) {
                foreach ($permissions as $permission) {
                    $attachRows[] = [
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            } else {
                foreach ($defaults as $code) {
                    $permission = $permissions->get($code);
                    if (! $permission) {
                        continue;
                    }

                    $attachRows[] = [
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($attachRows === []) {
                continue;
            }

            DB::table('permission_role')->upsert(
                $attachRows,
                ['role_id', 'permission_id'],
                ['updated_at']
            );
        }
    }

    private function ensureSuperAdminSafety(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_super_admin')) {
            return;
        }

        if (DB::table('users')->where('is_super_admin', 1)->exists()) {
            return;
        }

        $adminUserId = DB::table('users')
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('id')
            ->value('id');

        if ($adminUserId !== null) {
            DB::table('users')
                ->where('id', $adminUserId)
                ->update(['is_super_admin' => 1]);

            return;
        }

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId !== null) {
            DB::table('users')
                ->where('id', $firstUserId)
                ->update([
                    'role' => User::ROLE_ADMIN,
                    'is_super_admin' => 1,
                ]);
        }
    }
};
