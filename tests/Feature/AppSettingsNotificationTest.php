<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hrd_can_open_notification_settings_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HRD]);

        $this->actingAs($user)
            ->get(route('settings.notifications'))
            ->assertOk()
            ->assertSee('Pengaturan Notifikasi')
            ->assertSee('WhatsApp Official API');
    }

    public function test_hrd_can_save_notification_preferences(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HRD]);

        $this->actingAs($user)
            ->post(route('settings.notifications.update'), [
                'events' => [
                    'candidate_status_shortlisted' => ['email' => '1'],
                    'candidate_status_accepted' => ['email' => '1'],
                    'employee_profile_change_reviewed' => ['internal' => '1', 'email' => '1', 'whatsapp' => '1'],
                ],
                'templates' => [
                    'candidate_status_shortlisted' => ['title' => 'Shortlist', 'body' => 'Halo {name}'],
                    'candidate_status_accepted' => ['title' => 'Accepted', 'body' => 'Selamat {name}'],
                    'employee_profile_change_reviewed' => ['title' => 'Review', 'body' => 'Halo {name}, {message}'],
                ],
                'notification_whatsapp_provider' => 'official_api',
                'notification_whatsapp_api_version' => 'v23.0',
                'notification_whatsapp_business_account_id' => 'biz-1',
                'notification_whatsapp_phone_number_id' => 'phone-1',
                'notification_whatsapp_access_token' => 'secret-token',
                'notification_whatsapp_default_country_code' => '62',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $setting = \App\Models\AppSetting::query()->firstOrFail();
        $this->assertSame('official_api', $setting->notification_whatsapp_provider);
        $this->assertSame('phone-1', $setting->notification_whatsapp_phone_number_id);
        $this->assertSame('62', $setting->notification_whatsapp_default_country_code);
        $this->assertTrue((bool) data_get($setting->notification_event_preferences_json, 'employee_profile_change_reviewed.whatsapp'));
        $this->assertSame('Shortlist', data_get($setting->notification_templates_json, 'candidate_status_shortlisted.title'));
    }
}
