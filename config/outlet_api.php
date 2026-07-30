<?php

return [
    'direct_token' => env('OUTLET_API_DIRECT_TOKEN', env('OUTLET_API_TOKEN')),

    'login' => [
        'allowed_emails' => array_values(array_filter(array_map(
            static fn (string $email): string => mb_strtolower(trim($email)),
            explode(',', (string) env('OUTLET_API_LOGIN_ALLOWED_EMAILS', ''))
        ))),
        'allowed_roles' => array_values(array_filter(array_map(
            static fn (string $role): string => strtolower(trim($role)),
            explode(',', (string) env('OUTLET_API_LOGIN_ALLOWED_ROLES', ''))
        ))),
        'token_name' => trim((string) env('OUTLET_API_LOGIN_TOKEN_NAME', 'ocia-compat')),
        'token_expires_in_minutes' => ($value = trim((string) env('OUTLET_API_LOGIN_TOKEN_EXPIRES_IN_MINUTES', ''))) === ''
            ? null
            : (int) $value,
    ],
];
