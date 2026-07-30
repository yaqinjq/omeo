<?php

use App\Support\ApplicationFormUploadLimit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'api.token' => \App\Http\Middleware\EnsureValidApiToken::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->expectsJson() && $request->routeIs('application-form.update') && $exception->response === null) {
                return redirect()
                    ->back()
                    ->withInput($request->except(['photo_ktp_file', 'scan_ktp_file', 'cv_file']))
                    ->withErrors($exception->errors())
                    ->with('error', 'Periksa kembali field yang ditandai merah sebelum mengirim form.');
            }

            return null;
        });

        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if ($request->routeIs('application-form.upload-temp') || $request->is('application-form/upload-temp')) {
                $limit = ApplicationFormUploadLimit::humanReadableEffectiveLimit();

                return response()->json([
                    'ok' => false,
                    'message' => 'Ukuran upload melebihi batas server (' . $limit . '). Cek upload_max_filesize, post_max_size, dan client_max_body_size di server production.',
                    'reason' => 'server_limit',
                    'request_id' => (string) \Illuminate\Support\Str::uuid(),
                    'errors' => [
                        'document' => ['Ukuran upload melebihi batas server (' . $limit . ').'],
                    ],
                ], 422);
            }

            if (! $request->expectsJson() && ($request->routeIs('application-form.update') || $request->is('application-form'))) {
                $limit = ApplicationFormUploadLimit::humanReadableEffectiveLimit();
                $message = 'Ukuran total upload melebihi batas server (' . $limit . '). Kurangi ukuran file lalu coba lagi.';

                return redirect()
                    ->route('application-form.edit')
                    ->with('error', $message)
                    ->withErrors(['photo_ktp_file' => $message])
                    ->with('first_error_step', 1);
            }

            return null;
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->routeIs('application-form.upload-temp') || $request->is('application-form/upload-temp')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Sesi halaman sudah kedaluwarsa. Refresh halaman lalu upload ulang.',
                    'reason' => 'csrf_token_mismatch',
                    'request_id' => (string) \Illuminate\Support\Str::uuid(),
                    'errors' => [
                        'session' => ['Sesi halaman sudah kedaluwarsa.'],
                    ],
                ], 419);
            }

            if (! $request->expectsJson() && ($request->routeIs('application-form.update') || $request->is('application-form'))) {
                $contentLength = (int) $request->server('CONTENT_LENGTH');
                $effectiveLimit = ApplicationFormUploadLimit::effectiveBytes();
                $limit = ApplicationFormUploadLimit::humanReadableEffectiveLimit();
                $message = $effectiveLimit > 0 && $contentLength > $effectiveLimit
                    ? 'Ukuran data yang dikirim melebihi batas server (' . $limit . '). Upload dokumen cepat belum selesai, jadi file ikut terkirim bersama form. Kompres file atau unggah dokumen satu per satu sampai statusnya siap dipakai.'
                    : 'Sesi halaman sudah kedaluwarsa saat mengirim form. Silakan login/refresh halaman, lalu kirim ulang setelah dokumen berstatus siap dipakai.';

                return redirect()
                    ->route('application-form.edit')
                    ->with('error', $message)
                    ->withErrors(['photo_ktp_file' => $message])
                    ->with('first_error_step', 1);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->routeIs('application-form.upload-temp') || $request->is('application-form/upload-temp')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Sesi login sudah habis. Silakan login ulang lalu upload kembali.',
                    'reason' => 'unauthenticated',
                    'request_id' => (string) \Illuminate\Support\Str::uuid(),
                    'errors' => [
                        'session' => ['Sesi login sudah habis.'],
                    ],
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->routeIs('application-form.upload-temp') || $request->is('application-form/upload-temp') || $request->routeIs('application-form.update') || $request->is('application-form')) {
                $requestId = (string) \Illuminate\Support\Str::uuid();

                \Illuminate\Support\Facades\Log::warning('Application form authorization/403', [
                    'request_id' => $requestId,
                    'user_id' => $request->user()?->id,
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'submit_mode' => $request->boolean('final_submit') ? 'final' : 'draft',
                    'message' => $exception->getMessage(),
                    'ip' => $request->ip(),
                    'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 500),
                ]);

                if ($request->expectsJson() || $request->routeIs('application-form.upload-temp') || $request->is('application-form/upload-temp')) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Akses application form ditolak. Silakan login ulang. Jika tetap terjadi, hubungi HRD dengan kode: ' . $requestId,
                        'reason' => 'forbidden',
                        'request_id' => $requestId,
                        'errors' => [
                            'authorization' => ['Akses application form ditolak.'],
                        ],
                    ], 403);
                }

                return redirect()
                    ->route('application-form.edit')
                    ->with('error', 'Akses application form ditolak. Silakan login ulang. Jika tetap terjadi, hubungi HRD dengan kode: ' . $requestId);
            }

            return null;
        });

        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 403) {
                return null;
            }

            if ($request->routeIs('application-form.upload-temp') || $request->is('application-form/upload-temp') || $request->routeIs('application-form.update') || $request->is('application-form')) {
                $requestId = (string) \Illuminate\Support\Str::uuid();

                \Illuminate\Support\Facades\Log::warning('Application form authorization/403', [
                    'request_id' => $requestId,
                    'user_id' => $request->user()?->id,
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'submit_mode' => $request->boolean('final_submit') ? 'final' : 'draft',
                    'message' => $exception->getMessage(),
                    'ip' => $request->ip(),
                    'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 500),
                ]);

                if ($request->expectsJson() || $request->routeIs('application-form.upload-temp') || $request->is('application-form/upload-temp')) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Akses application form ditolak. Silakan login ulang. Jika tetap terjadi, hubungi HRD dengan kode: ' . $requestId,
                        'reason' => 'forbidden',
                        'request_id' => $requestId,
                        'errors' => [
                            'authorization' => ['Akses application form ditolak.'],
                        ],
                    ], 403);
                }

                return redirect()
                    ->route('application-form.edit')
                    ->with('error', 'Akses application form ditolak. Silakan login ulang. Jika tetap terjadi, hubungi HRD dengan kode: ' . $requestId);
            }

            return null;
        });
    })->create();
