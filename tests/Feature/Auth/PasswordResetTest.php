<?php

namespace Tests\Feature\Auth;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.example.test');
        Config::set('mail.mailers.smtp.port', 1025);
        Config::set('mail.from.address', 'hr@example.test');
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $this->get('/reset-password/' . $notification->token)->assertStatus(200);
            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasNoErrors()->assertRedirect(route('login'));
            return true;
        });
    }

    public function test_reset_password_request_can_use_database_backed_smtp_configuration(): void
    {
        Notification::fake();

        AppSetting::query()->create([
            'app_name' => 'OMEO HR Suite',
            'meta_title' => 'Portal Rekrutmen & HRIS',
            'retention_enabled' => true,
            'retention_rejected_days' => 30,
            'retention_blocked_days' => 365,
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.database.test',
            'mail_port' => 587,
            'mail_username' => 'database@example.test',
            'mail_password' => 'database-secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'database@example.test',
            'mail_from_name' => 'Database Mailer',
        ]);

        Config::set('mail.default', 'log');
        Config::set('mail.from.address', 'hello@example.com');

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);
    }
}
