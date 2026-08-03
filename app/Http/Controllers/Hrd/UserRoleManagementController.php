<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\HRD\UpdateUserRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\PermissionMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserRoleManagementController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('applicantProfile:id,user_id,personal_json')
            ->select(['id', 'name', 'email', 'role', 'is_super_admin', 'employee_id', 'created_at'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('applicantProfile', function ($profileQuery) use ($search) {
                            $driver = $profileQuery->getConnection()->getDriverName();

                            if ($driver === 'mysql') {
                                $profileQuery->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(personal_json, '$.ktp_number')) like ?", ["%{$search}%"])
                                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(personal_json, '$.nik')) like ?", ["%{$search}%"]);

                                return;
                            }

                            $profileQuery->whereRaw("json_extract(personal_json, '$.ktp_number') like ?", ["%{$search}%"])
                                ->orWhereRaw("json_extract(personal_json, '$.nik') like ?", ["%{$search}%"])
                                ->orWhere('personal_json', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $roles = collect();
        $permissionsGrouped = collect();

        if (Schema::hasTable('roles')) {
            $roles = Role::query()
                ->with(['permissions:id,slug'])
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'description', 'is_system']);
        }

        if (Schema::hasTable('permissions')) {
            $permissionMeta = PermissionMap::permissionMeta();
            $permissions = Permission::query()
                ->orderBy('group_name')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'group']);

            $permissionsGrouped = $permissions
                ->groupBy(function (Permission $permission) use ($permissionMeta): string {
                    return (string) data_get($permissionMeta, $permission->code.'.group', $permission->group_name ?: 'General');
                })
                ->map(function ($items) use ($permissionMeta) {
                    return $items->map(function (Permission $permission) use ($permissionMeta) {
                        return [
                            'code' => $permission->code,
                            'label' => (string) data_get($permissionMeta, $permission->code.'.label', $permission->name),
                        ];
                    })->values();
                })
                ->sortKeys();
        }

        if ($roles->isEmpty()) {
            $legacyLabels = User::roleLabels();
            $roles = collect(User::ALLOWED_ROLES)->map(function (string $slug) use ($legacyLabels) {
                $role = new Role();
                $role->id = 0;
                $role->slug = $slug;
                $role->name = $legacyLabels[$slug] ?? ucfirst($slug);
                $role->description = 'Legacy role';
                $role->is_system = true;
                $role->setRelation('permissions', collect());

                return $role;
            });
        }

        return view('hrd.users.index', [
            'users' => $users,
            'search' => $search,
            'roleLabels' => User::roleLabels(),
            'roleOptions' => $roles->pluck('slug')->values()->all(),
            'roles' => $roles,
            'permissionsGrouped' => $permissionsGrouped,
            'canManageRoles' => $this->actorCanManageRoles($request->user()),
            'rbacReady' => Schema::hasTable('roles') && Schema::hasTable('permissions') && Schema::hasTable('permission_role'),
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $newRole = strtolower((string) $request->validated('role'));
        $currentRole = $user->normalizedRole();

        if (! $this->actorCanManageRoles($actor)) {
            return back()->withErrors([
                'role' => 'Anda tidak memiliki izin untuk mengubah role user.',
            ]);
        }

        if ((bool) $user->is_super_admin) {
            if ($newRole !== User::ROLE_ADMIN) {
                return back()->withErrors([
                    'role' => 'Role akun super-admin tidak boleh diubah dari admin.',
                ]);
            }
        }

        if ((int) $actor?->id === (int) $user->id && $currentRole === User::ROLE_ADMIN && $newRole !== User::ROLE_ADMIN) {
            return back()->withErrors([
                'role' => 'Perubahan ditolak untuk mencegah lock-out akun admin Anda.',
            ]);
        }

        if ($currentRole === User::ROLE_ADMIN && $newRole !== User::ROLE_ADMIN) {
            $otherAdminExists = User::query()
                ->whereKeyNot($user->id)
                ->where('role', User::ROLE_ADMIN)
                ->exists();

            if (! $otherAdminExists) {
                return back()->withErrors([
                    'role' => 'Minimal harus ada 1 akun admin aktif.',
                ]);
            }
        }

        if ($currentRole !== $newRole) {
            $payload = ['role' => $newRole];

            if ((bool) $user->is_super_admin) {
                $payload['role'] = User::ROLE_ADMIN;
            }

            $user->forceFill($payload)->save();
        }

        return back()->with('success', 'Role user berhasil diperbarui.');
    }

    public function updateEmail(Request $request, User $user): RedirectResponse
    {
        if (! $this->actorCanManageRoles($request->user())) {
            return back()->withErrors([
                'email' => 'Anda tidak memiliki izin untuk mengubah email login user.',
            ]);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $oldEmail = $user->email;

        if ($oldEmail !== $data['email']) {
            $user->forceFill(['email' => $data['email']])->save();
        }

        return back()->with('success', "Email login {$user->name} berhasil diubah dari {$oldEmail} ke {$data['email']}.");
    }

    public function storeRole(Request $request): RedirectResponse
    {
        if (! $this->actorCanManageRoles($request->user())) {
            abort(403);
        }

        if (! Schema::hasTable('roles')) {
            return back()->withErrors(['role_store' => 'RBAC belum siap. Jalankan migrasi terbaru terlebih dahulu.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('roles', 'slug'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $slug = strtolower(trim((string) $validated['slug']));

        Role::query()->create([
            'name' => trim((string) $validated['name']),
            'slug' => $slug,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'is_system' => false,
        ]);

        return back()->with('success', 'Role baru berhasil dibuat.');
    }

    public function updateRoleDefinition(Request $request, Role $role): RedirectResponse
    {
        if (! $this->actorCanManageRoles($request->user())) {
            abort(403);
        }

        $isSystemRole = (bool) $role->is_system || PermissionMap::isSystemRole($role->slug);

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ];

        if (! $isSystemRole) {
            $rules['slug'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('roles', 'slug')->ignore($role->id),
            ];
        }

        $validated = $request->validate($rules);

        $payload = [
            'name' => trim((string) $validated['name']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
        ];

        if (! $isSystemRole) {
            $newSlug = strtolower(trim((string) $validated['slug']));
            $oldSlug = $role->slug;

            if ($newSlug !== $oldSlug) {
                User::query()->where('role', $oldSlug)->update(['role' => $newSlug]);
                $payload['slug'] = $newSlug;
            }
        }

        $role->update($payload);

        return back()->with('success', 'Role berhasil diperbarui.');
    }

    public function destroyRole(Request $request, Role $role): RedirectResponse
    {
        if (! $this->actorCanManageRoles($request->user())) {
            abort(403);
        }

        if ((bool) $role->is_system || PermissionMap::isSystemRole($role->slug)) {
            return back()->withErrors([
                'role_delete' => 'Role sistem tidak boleh dihapus.',
            ]);
        }

        $hasUsers = User::query()->where('role', $role->slug)->exists();
        if ($hasUsers) {
            return back()->withErrors([
                'role_delete' => 'Role tidak dapat dihapus karena masih dipakai user.',
            ]);
        }

        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }

    public function syncRolePermissions(Request $request, Role $role): RedirectResponse
    {
        if (! $this->actorCanManageRoles($request->user())) {
            abort(403);
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return back()->withErrors(['role_permissions' => 'RBAC belum siap. Jalankan migrasi terbaru terlebih dahulu.']);
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'code')],
        ]);

        $selectedCodes = collect((array) ($validated['permissions'] ?? []))
            ->map(static fn ($code) => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        if ($role->slug === User::ROLE_ADMIN) {
            $selectedCodes = collect(PermissionMap::allPermissionCodes());
        }

        $permissionIds = Permission::query()
            ->whereIn('code', $selectedCodes->all())
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);

        return back()->with('success', 'Permission role berhasil diperbarui.');
    }

    private function actorCanManageRoles(?User $user): bool
    {
        return $user instanceof User
            && ($user->hasPermission('users_roles.manage') || $user->hasRole(User::ROLE_ADMIN));
    }
}
