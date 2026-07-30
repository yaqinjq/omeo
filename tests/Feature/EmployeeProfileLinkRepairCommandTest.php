<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeProfileLinkRepairCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureEmployeesTable();
    }

    public function test_repair_command_links_candidate_and_user_for_legacy_employee_profile(): void
    {
        $employee = Employee::query()->create([
            'nik' => '3175000000000017',
            'full_name' => null,
            'email_private' => 'legacy.repair@example.com',
            'status_employment' => 'probation',
        ]);

        $user = User::factory()->create([
            'email' => 'legacy.repair@example.com',
            'role' => User::ROLE_PROBATION,
            'employee_id' => null,
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Repair Legacy User',
                'email' => 'legacy.repair@example.com',
                'ktp_number' => '3175000000000017',
            ],
        ]);

        $candidate = Candidate::query()->create([
            'full_name' => 'Repair Legacy User',
            'email' => 'legacy.repair@example.com',
            'nik' => '3175000000000017',
            'status' => Candidate::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'user_id' => null,
        ]);

        $exitCode = Artisan::call('employees:repair-profile-links', [
            '--employee-id' => $employee->id,
        ]);

        $this->assertSame(0, $exitCode);

        $user->refresh();
        $candidate->refresh();

        $this->assertSame($employee->id, $user->employee_id);
        $this->assertSame($user->id, $candidate->user_id);
    }

    private function ensureEmployeesTable(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('nik')->nullable();
                $table->string('external_id')->nullable();
                $table->string('full_name')->nullable();
                $table->string('email_private')->nullable();
                $table->string('phone_number')->nullable();
                $table->date('join_date')->nullable();
                $table->date('probation_end_date')->nullable();
                $table->string('status_employment')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
}
