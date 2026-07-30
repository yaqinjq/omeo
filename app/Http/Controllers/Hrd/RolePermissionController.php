<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $roles = Role::query()
            ->withCount('permissions')
            ->when($q !== '', fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%"))
            ->orderByDesc('is_super_admin')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('hrd.roles.index', [
            'roles' => $roles,
            'search' => $q,
        ]);
    }

    public function create()
    {
        return view('hrd.roles.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('roles', 'slug')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Role::query()->create([
            'name' => trim((string) $validated['name']),
            'slug' => strtolower(trim((string) $validated['slug'])),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'is_system' => false,
            'is_super_admin' => false,
        ]);

        return redirect()->route('hrd.roles.index')->with('success', 'Role berhasil dibuat.');
    }

    public function edit(Role $role)
    {
        return view('hrd.roles.edit', ['role' => $role]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($role->is_super_admin && (int) User::query()->where('role', $role->slug)->count() < 1) {
            return back()->withErrors(['role' => 'Role super-admin wajib tetap memiliki user.']);
        }

        if ($role->is_system || $role->is_super_admin) {
            $validated['slug'] = $role->slug;
        }

        $newSlug = strtolower(trim((string) $validated['slug']));
        $oldSlug = $role->slug;

        $role->update([
            'name' => trim((string) $validated['name']),
            'slug' => $newSlug,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
        ]);

        if ($oldSlug !== $newSlug) {
            User::query()->where('role', $oldSlug)->update(['role' => $newSlug]);
        }

        return redirect()->route('hrd.roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function permissions(Role $role)
    {
        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'group']);

        $grouped = $permissions->groupBy(fn (Permission $permission) => $permission->group ?: 'General');
        $current = $role->permissions()->pluck('permissions.slug')->all();

        return view('hrd.roles.permissions', [
            'role' => $role,
            'permissionsGrouped' => $grouped,
            'currentSlugs' => $current,
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        if (! Schema::hasTable('permissions')) {
            return back()->withErrors(['permissions' => 'Tabel permissions belum tersedia.']);
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'slug')],
        ]);

        $selected = collect((array) ($validated['permissions'] ?? []))
            ->map(fn ($item) => strtolower(trim((string) $item)))
            ->filter()
            ->unique()
            ->values();

        if ($role->is_super_admin || $role->slug === 'admin') {
            $selected = Permission::query()->pluck('slug');
        }

        $ids = Permission::query()->whereIn('slug', $selected->all())->pluck('id')->all();
        $role->permissions()->sync($ids);

        return back()->with('success', 'Mapping permission berhasil disimpan.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system || $role->is_super_admin) {
            return back()->withErrors(['role' => 'Role system/super-admin tidak boleh dihapus.']);
        }

        if (User::query()->where('role', $role->slug)->exists()) {
            return back()->withErrors(['role' => 'Role tidak dapat dihapus karena masih dipakai user.']);
        }

        $role->delete();

        return redirect()->route('hrd.roles.index')->with('success', 'Role berhasil dihapus.');
    }
}
