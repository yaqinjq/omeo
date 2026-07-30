<?php

namespace App\Support\Rbac;

use Illuminate\Support\Str;

class PermissionMap
{
    public static function allPermissionCodes(): array
    {
        return array_keys((array) config('rbac.permissions', []));
    }

    public static function permissionMeta(): array
    {
        return (array) config('rbac.permissions', []);
    }

    public static function requiredForRoute(?string $routeName): array
    {
        if (! is_string($routeName) || $routeName === '') {
            return [];
        }

        $required = [];
        foreach ((array) config('rbac.route_permissions', []) as $pattern => $permissions) {
            if (Str::is((string) $pattern, $routeName)) {
                foreach ((array) $permissions as $permission) {
                    $permission = strtolower(trim((string) $permission));
                    if ($permission !== '') {
                        $required[] = $permission;
                    }
                }
            }
        }

        return array_values(array_unique($required));
    }

    public static function defaultPermissionsForRole(string $roleSlug): array
    {
        $key = strtolower(trim($roleSlug));
        $items = (array) data_get(config('rbac.legacy_role_permissions', []), $key, []);

        return array_values(array_unique(array_filter(array_map(
            static fn ($item) => strtolower(trim((string) $item)),
            $items
        ))));
    }

    public static function isSystemRole(string $roleSlug): bool
    {
        return array_key_exists(
            strtolower(trim($roleSlug)),
            (array) config('rbac.system_roles', [])
        );
    }
}
