<?php

namespace Tests\Feature;

use App\Mail\PlainTextNotificationMail;
use App\Models\AppSetting;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\User;
use App\Services\Notifications\UnifiedNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnifiedNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureEmployeeTable();
    }

    public function test_candidate_shortlisted_notification_uses_email_only(): void
    {
        Mail::fake();

        AppSetting::query()->create([
            'app_name' => 'OMEO HR Suite',
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.test',
            'mail_port' => 587,
            'mail_from_address' => 'hr@example.test',
            'mail_from_name' => 'OMEO HR',
            'notification_event_preferences_json' => [
                'candidate_status_shortlisted' => ['internal' => false, 'email' => true, 'whatsapp' => false],
            ],
        ]);

        $candidate = Candidate::query()->create([
            'full_name' => 'Kandidat Email',
            'email' => 'candidate@example.test',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        app(UnifiedNotificationService::class)->notifyCandidateStatusChanged($candidate, Candidate::STATUS_APPLIED, Candidate::STATUS_SHORTLISTED);

        Mail::assertSent(PlainTextNotificationMail::class, 1);
        $this->assertDatabaseCount('hr_notifications', 0);
    }

    public function test_employee_review_notification_can_send_internal_email_and_whatsapp(): void
    {
        Mail::fake();
        Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        AppSetting::query()->create([
            'app_name' => 'OMEO HR Suite',
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.test',
            'mail_port' => 587,
            'mail_from_address' => 'hr@example.test',
            'mail_from_name' => 'OMEO HR',
            'notification_event_preferences_json' => [
                'employee_profile_change_reviewed' => ['internal' => true, 'email' => true, 'whatsapp' => true],
            ],
            'notification_whatsapp_provider' => 'official_api',
            'notification_whatsapp_api_version' => 'v23.0',
            'notification_whatsapp_phone_number_id' => '123456789',
            'notification_whatsapp_access_token' => 'token-123',
            'notification_whatsapp_default_country_code' => '62',
        ]);

        $employee = Employee::query()->create([
            'nik' => '3175000000000999',
            'full_name' => 'Pegawai Notifikasi',
            'phone_number' => '081234567890',
            'status_employment' => 'permanent',
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'employee_id' => $employee->id,
            'email' => 'employee@example.test',
        ]);

        app(UnifiedNotificationService::class)->notifyEmployeeProfileChangeReviewed(
            $user,
            'Perubahan Data Disetujui',
            'Perubahan data Anda sudah disetujui.',
            99,
            'approved'
        );

        Mail::assertSent(PlainTextNotificationMail::class, 1);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('hr_notifications', 1);
    }

    private function ensureEmployeeTable(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('nik')->nullable();
                $table->string('full_name')->nullable();
                $table->string('email_private')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('status_employment')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
}

