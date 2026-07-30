<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\MailConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsSmtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_index_redirects_to_general_submenu(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HRD]);

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertRedirect(route('settings.general'));
    }

    public function test_hrd_can_open_email_settings_submenu(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HRD]);

        $this->actingAs($user)
            ->get(route('settings.email'))
            ->assertOk()
            ->assertSee('SMTP & Email');
    }

    public function test_hrd_can_save_smtp_settings_from_email_submenu(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HRD]);

        $response = $this->actingAs($user)->post(route('settings.email.update'), [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.test',
            'mail_port' => '587',
            'mail_username' => 'hr@example.test',
            'mail_password' => 'smtp-secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'hr@example.test',
            'mail_from_name' => 'OMEO HR',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $setting = AppSetting::query()->firstOrFail();
        $this->assertSame('smtp', $setting->mail_mailer);
        $this->assertSame('smtp.example.test', $setting->mail_host);
        $this->assertSame(587, $setting->mail_port);
        $this->assertSame('hr@example.test', $setting->mail_username);
        $this->assertSame('tls', $setting->mail_encryption);
        $this->assertSame('hr@example.test', $setting->mail_from_address);
        $this->assertSame('OMEO HR', $setting->mail_from_name);
        $this->assertSame('smtp-secret', $setting->mail_password);
    }

    public function test_manager_cannot_overwrite_smtp_settings(): void
    {
        AppSetting::query()->create([
            'app_name' => 'OMEO HR Suite',
            'meta_title' => 'Portal Rekrutmen & HRIS',
            'retention_enabled' => true,
            'retention_rejected_days' => 30,
            'retention_blocked_days' => 365,
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.original.test',
            'mail_port' => 587,
            'mail_username' => 'original@example.test',
            'mail_password' => 'original-secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'original@example.test',
            'mail_from_name' => 'Original Sender',
        ]);

        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);

        $this->actingAs($manager)
            ->post(route('settings.email.update'), [
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.changed.test',
                'mail_port' => '2525',
                'mail_username' => 'changed@example.test',
                'mail_password' => 'changed-secret',
                'mail_encryption' => 'ssl',
                'mail_from_address' => 'changed@example.test',
                'mail_from_name' => 'Changed Sender',
            ])
            ->assertForbidden();

        $setting = AppSetting::query()->firstOrFail();
        $this->assertSame('smtp.original.test', $setting->mail_host);
        $this->assertSame(587, $setting->mail_port);
        $this->assertSame('original@example.test', $setting->mail_username);
        $this->assertSame('original@example.test', $setting->mail_from_address);
        $this->assertSame('Original Sender', $setting->mail_from_name);
        $this->assertSame('original-secret', $setting->mail_password);
    }

    public function test_tls_smtp_configuration_does_not_set_invalid_tls_scheme(): void
    {
        $setting = AppSetting::query()->create([
            'app_name' => 'OMEO HR Suite',
            'meta_title' => 'Portal Rekrutmen & HRIS',
            'retention_enabled' => true,
            'retention_rejected_days' => 30,
            'retention_blocked_days' => 365,
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_username' => 'hr@example.test',
            'mail_password' => 'smtp-secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'hr@example.test',
            'mail_from_name' => 'OMEO HR',
        ]);

        MailConfiguration::applyFromSettings($setting);

        $this->assertSame('smtp', config('mail.mailers.smtp.transport'));
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
        $this->assertNull(config('mail.mailers.smtp.scheme'));
    }

    public function test_ssl_smtp_configuration_uses_smtps_scheme(): void
    {
        $setting = AppSetting::query()->create([
            'app_name' => 'OMEO HR Suite',
            'meta_title' => 'Portal Rekrutmen & HRIS',
            'retention_enabled' => true,
            'retention_rejected_days' => 30,
            'retention_blocked_days' => 365,
            'mail_mailer' => 'smtp',
            'mail_host' => 'mail.example.test',
            'mail_port' => 465,
            'mail_username' => 'hr@example.test',
            'mail_password' => 'smtp-secret',
            'mail_encryption' => 'ssl',
            'mail_from_address' => 'hr@example.test',
            'mail_from_name' => 'OMEO HR',
        ]);

        MailConfiguration::applyFromSettings($setting);

        $this->assertSame('smtp', config('mail.mailers.smtp.transport'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }
}
