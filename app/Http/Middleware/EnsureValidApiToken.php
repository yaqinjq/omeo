<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureValidApiToken
{
    public function handle(Request $request, Closure $next): mixed
    {
        $providedToken = trim((string) $request->bearerToken());
        $directToken = trim((string) config('outlet_api.direct_token'));

        if ($providedToken === '') {
            return $this->unauthorized('missing_bearer_token', 'Send Authorization: Bearer <OUTLET_API_DIRECT_TOKEN>.');
        }

        if ($this->isDirectOutletRoute($request)) {
            if ($directToken === '') {
                return $this->directTokenNotConfigured();
            }

            if ($directToken !== '' && hash_equals($directToken, $providedToken)) {
                return $next($request);
            }

            return $this->unauthorized('invalid_outlet_api_token', 'Check the direct outlet bearer token configured in WIPRO.');
        }

        if ($this->isBranchesRoute($request) && $directToken !== '' && hash_equals($directToken, $providedToken)) {
            return $next($request);
        }

        if (! $this->isBranchesRoute($request) || ! Schema::hasTable('personal_access_tokens')) {
            return $this->unauthorized('invalid_outlet_api_token', 'This token is not allowed for the requested Outlet API endpoint.');
        }

        $accessToken = PersonalAccessToken::findToken($providedToken);

        if (! $accessToken || $this->tokenIsExpired($accessToken) || ! $accessToken->can('outlet-api:branches')) {
            return $this->unauthorized('invalid_or_expired_branch_token', 'Login again through /api/login or use the direct outlet bearer token.');
        }

        $request->setUserResolver(fn () => $accessToken->tokenable);

        return $next($request);
    }

    private function isDirectOutletRoute(Request $request): bool
    {
        return $request->routeIs('api.outlets.index') || $request->routeIs('api.outlets.show');
    }

    private function isBranchesRoute(Request $request): bool
    {
        return $request->routeIs('api.branches.index');
    }

    private function tokenIsExpired(PersonalAccessToken $accessToken): bool
    {
        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return true;
        }

        $sanctumExpiration = config('sanctum.expiration');

        return is_int($sanctumExpiration)
            && $sanctumExpiration > 0
            && $accessToken->created_at !== null
            && $accessToken->created_at->addMinutes($sanctumExpiration)->isPast();
    }

    private function unauthorized(string $error, string $hint): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Unauthorized.',
            'error' => $error,
            'hint' => $hint,
        ], 401);
    }

    private function directTokenNotConfigured(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Outlet API direct token is not configured.',
            'error' => 'direct_token_not_configured',
            'hint' => 'Set OUTLET_API_DIRECT_TOKEN on the OMEO server before connecting WIPRO.',
        ], 503);
    }
}
