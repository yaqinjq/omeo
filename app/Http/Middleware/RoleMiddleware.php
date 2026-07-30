<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Rbac\PermissionMap;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $role = $user instanceof User
            ? $user->normalizedRole()
            : strtolower(trim((string) ($user->role ?? User::ROLE_APPLICANT)));

        if ($role === '') {
            $role = User::ROLE_APPLICANT;
        }

        $allowedRoles = [];
        foreach ($roles as $item) {
            foreach (explode(',', (string) $item) as $value) {
                $value = strtolower(trim($value));
                if ($value !== '') {
                    $allowedRoles[] = $value;
                }
            }
        }

        if (in_array($role, $allowedRoles, true)) {
            return $next($request);
        }

        // Backward-compatible bridge:
        // if route is mapped to permission(s), allow dynamic roles with matching permission.
        if ($user instanceof User) {
            $routeName = $request->route()?->getName();
            $requiredPermissions = PermissionMap::requiredForRoute($routeName);
            if ($requiredPermissions !== [] && $user->hasAnyPermission($requiredPermissions)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
