<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProbationOnboardingBankAccountFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_probation_can_submit_bank_accounts_and_hrd_can_approve_them(): void
    {
        Storage::fake('public');

        $employee = Employee::query()->create([
            'nik' => '3175000000000014',
            'full_name' => 'Probation Payroll',
            'status_employment' => 'probation',
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_PROBATION,
            'employee_id' => $employee->id,
        ]);

        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        $this->actingAs($user)
            ->post(route('probation-onboarding.update'), [
                'sim_number' => 'SIM-001',
                'npwp_number' => 'NPWP-001',
                'bpjs_kes_number' => 'KES-001',
                'bpjs_tk_number' => '',
                'passport_number' => '',
                'kk_number' => 'KK-001',
                'sim_file' => UploadedFile::fake()->image('sim.jpg'),
                'npwp_file' => UploadedFile::fake()->create('npwp.pdf', 100, 'application/pdf'),
                'bpjs_kes_file' => UploadedFile::fake()->create('bpjs-kes.pdf', 100, 'application/pdf'),
                'kk_file' => UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf'),
                'bank_accounts' => [
                    [
                        'bank_code' => 'bca',
                        'bank_name' => 'BCA',
                        'account_number' => '1234567890',
                        'account_holder_name' => 'Probation Payroll',
                        'is_primary' => '1',
                        'files' => [UploadedFile::fake()->image('rekening.jpg')],
                    ],
                ],
            ])
            ->assertRedirect(route('probation-onboarding.edit'))
            ->assertSessionHas('success');

        $changeRequest = ProfileChangeRequest::query()->latest('id')->firstOrFail();

        $this->actingAs($hrd)
            ->post(route('hrd.probation-verifications.approve', $changeRequest))
            ->assertRedirect(route('hrd.probation-verifications.show', $changeRequest))
            ->assertSessionHas('success');

        $changeRequest->refresh();
        $this->assertSame(ProfileChangeRequest::STATUS_APPROVED, $changeRequest->status);

        $bankAccount = EmployeeBankAccount::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($bankAccount);
        $this->assertSame('1234567890', $bankAccount->account_number);
        $this->assertTrue((bool) $bankAccount->is_primary);
        $this->assertNotEmpty($bankAccount->files()->first()?->file_path);
        Storage::disk('public')->assertExists($bankAccount->files()->first()->file_path);
    }

    private function ensureSupportTables(): void
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('outlets')) {
            Schema::create('outlets', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->integer('radius_meters')->nullable();
                $table->integer('geofence_radius_m')->nullable();
                $table->string('timezone')->nullable();
                $table->time('work_start_time')->nullable();
                $table->time('work_end_time')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('nik')->nullable();
                $table->string('employee_number')->nullable();
                $table->string('full_name')->nullable();
                $table->string('email_private')->nullable();
                $table->string('phone_number')->nullable();
                $table->date('join_date')->nullable();
                $table->date('probation_end_date')->nullable();
                $table->string('status_employment')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('outlet_id')->nullable();
                $table->string('jabatan')->nullable();
                $table->string('sim_number')->nullable();
                $table->string('sim_file_path')->nullable();
                $table->string('npwp_number')->nullable();
                $table->string('npwp_file_path')->nullable();
                $table->string('bpjs_kes_number')->nullable();
                $table->string('bpjs_kes_file_path')->nullable();
                $table->string('bpjs_tk_number')->nullable();
                $table->string('bpjs_tk_file_path')->nullable();
                $table->string('passport_number')->nullable();
                $table->string('passport_file_path')->nullable();
                $table->string('kk_number')->nullable();
                $table->string('kk_file_path')->nullable();
                $table->timestamp('payroll_verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
}
