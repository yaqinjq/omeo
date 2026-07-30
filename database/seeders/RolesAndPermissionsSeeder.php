<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => 'admin', 'name' => 'Admin', 'description' => 'System admin', 'is_system' => true, 'is_super_admin' => true],
            ['slug' => 'hrd', 'name' => 'HRD', 'description' => 'Human Resource Department', 'is_system' => true, 'is_super_admin' => false],
            ['slug' => 'manager', 'name' => 'Manager', 'description' => 'Operational manager', 'is_system' => true, 'is_super_admin' => false],
            ['slug' => 'applicant', 'name' => 'Applicant', 'description' => 'Default external applicant role', 'is_system' => true, 'is_super_admin' => false],
        ];

        Role::query()->upsert($roles, ['slug'], ['name', 'description', 'is_system', 'is_super_admin', 'updated_at']);

        $permissions = [
            ['slug' => 'applicants.view', 'name' => 'View Applicants', 'group' => 'Recruitment'],
            ['slug' => 'applicants.manage', 'name' => 'Manage Applicants', 'group' => 'Recruitment'],
            ['slug' => 'candidates.view', 'name' => 'View Candidates', 'group' => 'Recruitment'],
            ['slug' => 'candidates.manage', 'name' => 'Manage Candidates', 'group' => 'Recruitment'],
            ['slug' => 'tests.manage', 'name' => 'Manage Recruitment Tests', 'group' => 'Recruitment'],

            ['slug' => 'contracts.templates.manage', 'name' => 'Manage Contract Templates', 'group' => 'Contracts'],
            ['slug' => 'contracts.send', 'name' => 'Send Contracts', 'group' => 'Contracts'],
            ['slug' => 'contracts.review', 'name' => 'Review Contracts', 'group' => 'Contracts'],

            ['slug' => 'employees.view', 'name' => 'View Employees', 'group' => 'Employees'],
            ['slug' => 'employees.manage', 'name' => 'Manage Employees', 'group' => 'Employees'],
            ['slug' => 'profile_changes.review', 'name' => 'Review Profile Changes', 'group' => 'Employees'],

            ['slug' => 'settings.view', 'name' => 'View Settings', 'group' => 'Settings'],
            ['slug' => 'settings.manage', 'name' => 'Manage Settings', 'group' => 'Settings'],
            ['slug' => 'users_roles.manage', 'name' => 'Manage Users Roles', 'group' => 'Settings'],
        ];

        foreach ($permissions as &$permission) {
            $permission['description'] = $permission['name'];
            $permission['code'] = $permission['slug'];
            $permission['group_name'] = $permission['group'];
        }
        unset($permission);

        Permission::query()->upsert(
            $permissions,
            ['slug'],
            ['name', 'group', 'description', 'code', 'group_name', 'updated_at']
        );

        $bySlug = Permission::query()->pluck('id', 'slug');

        $admin = Role::query()->where('slug', 'admin')->first();
        $hrd = Role::query()->where('slug', 'hrd')->first();
        $manager = Role::query()->where('slug', 'manager')->first();

        if ($admin) {
            $admin->permissions()->sync($bySlug->values()->all());
        }

        if ($hrd) {
            $hrd->permissions()->sync($bySlug->only([
                'applicants.view',
                'applicants.manage',
                'candidates.view',
                'candidates.manage',
                'tests.manage',
                'contracts.templates.manage',
                'contracts.send',
                'contracts.review',
                'employees.view',
                'employees.manage',
                'profile_changes.review',
                'settings.view',
                'settings.manage',
                'users_roles.manage',
            ])->values()->all());
        }

        if ($manager) {
            $manager->permissions()->sync($bySlug->only([
                'applicants.view',
                'candidates.view',
                'employees.view',
                'settings.view',
            ])->values()->all());
        }

        // Safety net: ensure at least one super-admin user always exists.
        if (! User::query()->where('is_super_admin', true)->exists()) {
            $candidate = User::query()->whereKey(1)->first()
                ?? User::query()->where('role', 'admin')->orderBy('id')->first()
                ?? User::query()->orderBy('id')->first();

            if ($candidate) {
                $candidate->forceFill([
                    'role' => 'admin',
                    'is_super_admin' => true,
                ])->save();
            }
        }

        // Keep role slug usage backward-compatible (users.role string).
        DB::table('users')->whereNull('role')->orWhere('role', '')->update(['role' => 'applicant']);
    }
}
