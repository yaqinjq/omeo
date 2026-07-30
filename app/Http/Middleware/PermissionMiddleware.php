<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$arguments)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if (! $user instanceof User) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        [$mode, $permissions] = $this->parseArguments($arguments);

        if ($permissions === []) {
            return $next($request);
        }

        $allowed = $mode === 'any'
            ? $user->hasAnyPermission($permissions)
            : $user->hasAllPermissions($permissions);

        if ($allowed) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki izin untuk mengakses fitur ini.');
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @return array{0:string,1:array<int,string>}
     */
    private function parseArguments(array $arguments): array
    {
        $mode = 'all';
        $permissions = [];

        foreach ($arguments as $argument) {
            $raw = strtolower(trim((string) $argument));
            if ($raw === '') {
                continue;
            }

            if (str_starts_with($raw, 'any:')) {
                $mode = 'any';
                $raw = substr($raw, 4);
            } elseif (str_starts_with($raw, 'all:')) {
                $mode = 'all';
                $raw = substr($raw, 4);
            }

            foreach (explode(',', $raw) as $item) {
                $item = strtolower(trim($item));
                if ($item !== '') {
                    $permissions[] = $item;
                }
            }
        }

        return [$mode, array_values(array_unique($permissions))];
    }
}
