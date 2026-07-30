<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class OutletAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Schema::hasTable('personal_access_tokens')) {
            return response()->json([
                'message' => 'Outlet API login token storage is not configured.',
            ], 503);
        }

        $allowedEmails = config('outlet_api.login.allowed_emails', []);
        $allowedRoles = config('outlet_api.login.allowed_roles', []);

        if ($allowedEmails === [] && $allowedRoles === []) {
            return response()->json([
                'message' => 'Outlet API login is not configured.',
            ], 503);
        }

        $email = mb_strtolower(trim((string) $validated['email']));
        $password = (string) $validated['password'];

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            return response()->json([
                'message' => 'Invalid API credentials.',
            ], 401);
        }

        if (! $this->userIsAllowed($user, $allowedEmails, $allowedRoles)) {
            return response()->json([
                'message' => 'This user is not allowed to access the Outlet API.',
            ], 403);
        }

        $tokenName = trim((string) config('outlet_api.login.token_name', 'ocia-compat'));
        $expiresInMinutes = config('outlet_api.login.token_expires_in_minutes');
        $expiresAt = is_int($expiresInMinutes) && $expiresInMinutes > 0
            ? now()->addMinutes($expiresInMinutes)
            : null;

        $user->tokens()->where('name', $tokenName)->delete();

        $createdToken = $user->createToken($tokenName, ['outlet-api:branches'], $expiresAt);
        $plainTextToken = $createdToken->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'access_token' => $plainTextToken,
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'endpoints' => [
                'branches' => url('/api/branches'),
                'outlets' => url('/api/outlets'),
            ],
        ]);
    }

    private function userIsAllowed(User $user, array $allowedEmails, array $allowedRoles): bool
    {
        $email = mb_strtolower(trim((string) $user->email));
        $role = strtolower(trim((string) ($user->role ?? '')));

        if ($email !== '' && in_array($email, $allowedEmails, true)) {
            return true;
        }

        return $role !== '' && in_array($role, $allowedRoles, true);
    }
}
