<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Notifications\NotificationSettingsService;
use App\Support\MailConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppSettingController extends Controller
{
    public function __construct(private readonly NotificationSettingsService $notificationSettingsService)
    {
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('settings.general');
    }

    public function general(): View
    {
        $setting = $this->resolveSetting();

        return view('settings.general', [
            'setting' => $setting,
            'canManageSmtp' => $this->canManageSmtp(request()),
            'canManageNotifications' => $this->canManageNotifications(request()),
            'smtpConfigured' => MailConfiguration::isConfigured($setting),
            'activeSettingsTab' => 'general',
        ]);
    }

    public function email(): View
    {
        $setting = $this->resolveSetting();

        return view('settings.email', [
            'setting' => $setting,
            'canManageSmtp' => $this->canManageSmtp(request()),
            'smtpConfigured' => MailConfiguration::isConfigured($setting),
            'activeSettingsTab' => 'email',
        ]);
    }

    public function notifications(): View
    {
        $setting = $this->resolveSetting();
        $notificationSettings = $this->notificationSettingsService->resolve($setting);

        return view('settings.notifications', [
            'setting' => $setting,
            'notificationSettings' => $notificationSettings,
            'notificationLabels' => $this->notificationSettingsService->settingFormLabels(),
            'canManageNotifications' => $this->canManageNotifications(request()),
            'activeSettingsTab' => 'notifications',
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $setting = $this->resolveSetting();

        $data = $request->validate([
            'app_name' => 'required|string|max:50',
            'meta_title' => 'nullable|string|max:100',
            'meta_description' => 'nullable|string|max:255',
            'app_logo' => 'nullable|image|max:2048',
            'app_favicon' => 'nullable|image|mimes:ico,png,jpg|max:1024',
            'retention_enabled' => 'nullable|boolean',
            'retention_rejected_days' => 'required|integer|min:1|max:3650',
            'retention_blocked_days' => 'required|integer|min:1|max:3650',
        ]);

        if ($request->hasFile('app_logo')) {
            if ($setting->app_logo_path) {
                Storage::disk('public')->delete($setting->app_logo_path);
            }
            $setting->app_logo_path = $request->file('app_logo')->store('settings', 'public');
        }

        if ($request->hasFile('app_favicon')) {
            if ($setting->app_favicon_path) {
                Storage::disk('public')->delete($setting->app_favicon_path);
            }
            $setting->app_favicon_path = $request->file('app_favicon')->store('settings', 'public');
        }

        $setting->app_name = $data['app_name'];
        $setting->meta_title = $data['meta_title'] ?? null;
        $setting->meta_description = $data['meta_description'] ?? null;
        $setting->retention_enabled = $request->boolean('retention_enabled');
        $setting->retention_rejected_days = (int) $data['retention_rejected_days'];
        $setting->retention_blocked_days = (int) $data['retention_blocked_days'];
        $setting->save();

        return back()->with('success', 'Pengaturan umum aplikasi berhasil diperbarui.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        abort_unless($this->canManageSmtp($request), 403);

        $setting = $this->resolveSetting();
        $smtpData = $this->validateEmailSettings($request, false);

        $this->fillEmailSettings($setting, $smtpData);
        $setting->save();

        return back()->with('success', 'Konfigurasi SMTP berhasil diperbarui.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        abort_unless($this->canManageSmtp($request), 403);

        $setting = $this->resolveSetting();
        $smtpData = $this->validateEmailSettings($request, true);

        $this->fillEmailSettings($setting, $smtpData);
        $setting->save();

        MailConfiguration::applyFromSettings($setting);

        if (! MailConfiguration::isConfigured($setting)) {
            return back()->withErrors([
                'mail_host' => 'Konfigurasi SMTP belum lengkap. Lengkapi host, port, mailer, from email, dan field wajib lainnya sebelum mengirim email percobaan.',
            ]);
        }

        try {
            Mail::raw(
                'Email percobaan dari OMEO HR Suite berhasil dikirim. Jika Anda menerima email ini, berarti konfigurasi SMTP sudah aktif dan siap dipakai untuk reset password kandidat.',
                function ($message) use ($request, $setting): void {
                    $message
                        ->to((string) $request->input('smtp_test_recipient'))
                        ->subject('Uji SMTP OMEO HR Suite')
                        ->from(
                            (string) $setting->mail_from_address,
                            (string) ($setting->mail_from_name ?: $setting->app_name ?: config('app.name'))
                        );
                }
            );

            $setting->forceFill([
                'mail_test_last_status' => 'success',
                'mail_test_last_error' => null,
                'mail_test_last_email' => (string) $request->input('smtp_test_recipient'),
                'mail_test_last_ran_at' => now(),
            ])->save();

            return back()->with('success', 'Konfigurasi SMTP berhasil disimpan dan email percobaan berhasil dikirim.');
        } catch (\Throwable $exception) {
            report($exception);

            $setting->forceFill([
                'mail_test_last_status' => 'failed',
                'mail_test_last_error' => $exception->getMessage(),
                'mail_test_last_email' => (string) $request->input('smtp_test_recipient'),
                'mail_test_last_ran_at' => now(),
            ])->save();

            return back()->withErrors([
                'smtp_test_recipient' => 'Email percobaan gagal dikirim. Periksa host, port, username, password, enkripsi, dan koneksi SMTP Anda.',
            ]);
        }
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        abort_unless($this->canManageNotifications($request), 403);

        $setting = $this->resolveSetting();
        $validated = $request->validate([
            'events' => 'nullable|array',
            'events.*.internal' => 'nullable|boolean',
            'events.*.email' => 'nullable|boolean',
            'events.*.whatsapp' => 'nullable|boolean',
            'templates' => 'nullable|array',
            'templates.*.title' => 'nullable|string|max:255',
            'templates.*.body' => 'nullable|string|max:2000',
            'notification_whatsapp_provider' => 'required|string|in:official_api',
            'notification_whatsapp_api_version' => 'nullable|string|max:20',
            'notification_whatsapp_business_account_id' => 'nullable|string|max:255',
            'notification_whatsapp_phone_number_id' => 'nullable|string|max:255',
            'notification_whatsapp_access_token' => 'nullable|string|max:4000',
            'notification_whatsapp_default_country_code' => 'nullable|string|max:5',
        ]);

        $defaults = $this->notificationSettingsService->defaults();
        $events = $defaults['events'];
        foreach ($events as $eventKey => $channels) {
            $submitted = (array) ($validated['events'][$eventKey] ?? []);
            $events[$eventKey] = [
                'internal' => (bool) ($submitted['internal'] ?? false),
                'email' => (bool) ($submitted['email'] ?? false),
                'whatsapp' => (bool) ($submitted['whatsapp'] ?? false),
            ];
        }

        $templates = $defaults['templates'];
        foreach ($templates as $eventKey => $template) {
            $submitted = (array) ($validated['templates'][$eventKey] ?? []);
            $templates[$eventKey] = [
                'title' => trim((string) ($submitted['title'] ?? $template['title'])) ?: $template['title'],
                'body' => trim((string) ($submitted['body'] ?? $template['body'])) ?: $template['body'],
            ];
        }

        $setting->notification_event_preferences_json = $events;
        $setting->notification_templates_json = $templates;
        $setting->notification_whatsapp_provider = $validated['notification_whatsapp_provider'];
        $setting->notification_whatsapp_api_version = trim((string) ($validated['notification_whatsapp_api_version'] ?? 'v23.0')) ?: 'v23.0';
        $setting->notification_whatsapp_business_account_id = trim((string) ($validated['notification_whatsapp_business_account_id'] ?? '')) ?: null;
        $setting->notification_whatsapp_phone_number_id = trim((string) ($validated['notification_whatsapp_phone_number_id'] ?? '')) ?: null;
        $setting->notification_whatsapp_default_country_code = trim((string) ($validated['notification_whatsapp_default_country_code'] ?? '62')) ?: '62';

        if (filled($validated['notification_whatsapp_access_token'] ?? null)) {
            $setting->notification_whatsapp_access_token = $validated['notification_whatsapp_access_token'];
        }

        $setting->save();

        return back()->with('success', 'Pengaturan notifikasi multikanal berhasil diperbarui.');
    }

    private function validateEmailSettings(Request $request, bool $testMode): array
    {
        return $request->validate([
            'mail_mailer' => 'required|in:smtp',
            'mail_host' => 'required|string|max:255',
            'mail_port' => 'required|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|in:tls,ssl',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
            'smtp_test_recipient' => $testMode ? 'required|email|max:255' : 'nullable|email|max:255',
        ], [
            'smtp_test_recipient.required' => 'Email tujuan percobaan wajib diisi untuk mengirim email uji SMTP.',
        ]);
    }

    private function fillEmailSettings(AppSetting $setting, array $smtpData): void
    {
        $setting->mail_mailer = $smtpData['mail_mailer'];
        $setting->mail_host = $smtpData['mail_host'];
        $setting->mail_port = (int) $smtpData['mail_port'];
        $setting->mail_username = $smtpData['mail_username'] ?? null;
        $setting->mail_encryption = $smtpData['mail_encryption'] ?? null;
        $setting->mail_from_address = $smtpData['mail_from_address'];
        $setting->mail_from_name = $smtpData['mail_from_name'];

        if (filled($smtpData['mail_password'] ?? null)) {
            $setting->mail_password = $smtpData['mail_password'];
        }
    }

    private function resolveSetting(): AppSetting
    {
        return AppSetting::firstOrCreate([], [
            'app_name' => 'OMEO HR Suite',
            'meta_title' => 'Portal Rekrutmen & HRIS',
            'retention_enabled' => true,
            'retention_rejected_days' => 30,
            'retention_blocked_days' => 365,
        ]);
    }

    private function canManageSmtp(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user && ($user->isSuperAdmin() || $user->hasRole([User::ROLE_ADMIN, User::ROLE_HRD]));
    }

    private function canManageNotifications(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user && ($user->isSuperAdmin() || $user->hasRole([User::ROLE_ADMIN, User::ROLE_HRD]));
    }
}
