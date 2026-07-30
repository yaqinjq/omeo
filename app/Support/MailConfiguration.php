<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class MailConfiguration
{
    public static function applyFromSettings(?AppSetting $setting = null): void
    {
        $setting ??= self::resolveSettings();

        if (! $setting) {
            return;
        }

        $mailer = trim((string) ($setting->mail_mailer ?? ''));
        $host = trim((string) ($setting->mail_host ?? ''));
        $port = (int) ($setting->mail_port ?? 0);
        $fromAddress = trim((string) ($setting->mail_from_address ?? ''));

        if ($mailer === '' || $host === '' || $port <= 0 || $fromAddress === '') {
            return;
        }

        $encryption = self::normalizeEncryption($setting->mail_encryption ?? null);

        Config::set('mail.default', $mailer);
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', trim((string) ($setting->mail_username ?? '')) ?: null);
        Config::set('mail.mailers.smtp.password', trim((string) ($setting->mail_password ?? '')) ?: null);
        Config::set('mail.mailers.smtp.encryption', $encryption);
        Config::set('mail.mailers.smtp.scheme', $encryption === 'ssl' ? 'smtps' : null);
        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', trim((string) ($setting->mail_from_name ?? '')) ?: config('app.name'));

        try {
            if (app()->bound('mail.manager') && method_exists(app('mail.manager'), 'forgetMailers')) {
                app('mail.manager')->forgetMailers();
            }
        } catch (\Throwable) {
            // Ignore fake or mocked mail manager during tests.
        }
    }

    public static function isConfigured(?AppSetting $setting = null): bool
    {
        $setting ??= self::resolveSettings();

        if ($setting) {
            $mailer = trim((string) ($setting->mail_mailer ?? ''));
            $host = trim((string) ($setting->mail_host ?? ''));
            $port = (int) ($setting->mail_port ?? 0);
            $fromAddress = trim((string) ($setting->mail_from_address ?? ''));

            if ($mailer !== '' || $host !== '' || $port > 0 || $fromAddress !== '') {
                return $mailer !== ''
                    && in_array($mailer, ['smtp'], true)
                    && $host !== ''
                    && $port > 0
                    && $fromAddress !== '';
            }
        }

        $defaultMailer = trim((string) Config::get('mail.default'));
        if ($defaultMailer === '' || in_array($defaultMailer, ['log', 'array'], true)) {
            return false;
        }

        $mailerConfig = (array) Config::get('mail.mailers.' . $defaultMailer, []);
        $transport = trim((string) ($mailerConfig['transport'] ?? ''));
        $fromAddress = trim((string) Config::get('mail.from.address'));

        if ($fromAddress === '' || $fromAddress === 'hello@example.com') {
            return false;
        }

        if ($transport === 'smtp') {
            $host = trim((string) ($mailerConfig['host'] ?? ''));
            $port = (int) ($mailerConfig['port'] ?? 0);

            return $host !== '' && $host !== '127.0.0.1' && $port > 0;
        }

        return $transport !== '';
    }

    public static function resolveSettings(): ?AppSetting
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return AppSetting::query()->first();
    }

    private static function normalizeEncryption(mixed $value): ?string
    {
        $encryption = strtolower(trim((string) $value));

        return in_array($encryption, ['tls', 'ssl'], true) ? $encryption : null;
    }
}
