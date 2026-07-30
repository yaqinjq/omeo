<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeProfileChangeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_employee_can_submit_full_profile_change_and_hrd_can_approve_it(): void
    {
        $employee = Employee::query()->create([
            'nik' => '3175000000000013',
            'full_name' => 'Probation Lama',
            'email_private' => 'lama@example.com',
            'phone_number' => '081200000001',
            'status_employment' => 'probation',
        ]);

        $employeeUser = User::factory()->create([
            'role' => User::ROLE_PROBATION,
            'employee_id' => $employee->id,
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $employeeUser->id,
            'personal_json' => [
                'full_name' => 'Probation Lama',
                'ktp_number' => '3175000000000013',
                'place_of_birth' => 'Jakarta',
                'date_of_birth' => '2000-01-01',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'marital_status' => 'Menikah',
                'whatsapp' => '081200000001',
                'photo_path' => 'seed/photo.jpg',
                'ktp_path' => 'seed/ktp.pdf',
                'cv_path' => 'seed/cv.pdf',
                'reference_contacts' => [
                    ['name' => 'Lama Ref', 'relation' => 'Atasan', 'company' => 'Omeo', 'phone' => '081211111111'],
                ],
            ],
            'address_json' => [
                'ktp_address' => 'Alamat Lama KTP',
                'domicile_address' => 'Alamat Lama Domisili',
            ],
            'family_json' => [
                ['relation' => 'Istri', 'name' => 'Istri Lama', 'gender' => 'Perempuan', 'dob' => '2001-01-01', 'education' => 'S1', 'job' => 'Karyawan'],
            ],
            'education_json' => [
                ['level' => 'SMA', 'school' => 'SMA Lama', 'major' => 'IPA', 'year_in' => '2015', 'year_out' => '2018', 'gpa' => '8.5'],
            ],
            'language_json' => [
                ['language' => 'Indonesia', 'speaking' => 'Baik', 'writing' => 'Baik'],
            ],
            'work_json' => [
                ['company' => 'PT Lama', 'position' => 'Staff', 'date_start' => '2020-01-01', 'date_end' => '2021-01-01', 'salary' => '4000000', 'reason' => 'Pindah'],
            ],
            'medical_json' => [
                ['illness' => 'Flu', 'year' => '2022', 'hospitalized' => 'Tidak', 'note' => 'Sembuh'],
            ],
            'completed_at' => now(),
        ]);

        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        $this->actingAs($employeeUser)
            ->get(route('employee-profile.show'))
            ->assertOk()
            ->assertSeeText('Profil Saya');

        $payload = [
            'full_name' => 'Probation Baru',
            'ktp_number' => '3175000000000099',
            'email_private' => 'baru@example.com',
            'phone_number' => '081299999999',
            'place_of_birth' => 'Bandung',
            'date_of_birth' => '2000-02-02',
            'gender' => 'Laki-laki',
            'religion' => 'Islam',
            'blood_type' => 'O',
            'marital_status' => 'Menikah',
            'marriage_date' => '2024-01-01',
            'whatsapp' => '081299999999',
            'ktp_address' => 'Alamat KTP Baru',
            'ktp_province' => 'Jawa Barat',
            'ktp_city' => 'Bandung',
            'domicile_address' => 'Alamat Domisili Baru',
            'families' => [
                ['relation' => 'Istri', 'name' => 'Istri Baru', 'gender' => 'Perempuan', 'dob' => '2001-01-01', 'education' => 'S1', 'job' => 'Karyawan'],
                ['relation' => 'Anak', 'name' => 'Anak Kedua', 'gender' => 'Laki-laki', 'dob' => '2025-01-01', 'education' => 'Balita', 'job' => '-'],
            ],
            'educations' => [
                ['level' => 'S1', 'school' => 'Universitas Baru', 'major' => 'Manajemen', 'year_in' => '2019', 'year_out' => '2023', 'gpa' => '3.50'],
            ],
            'languages' => [
                ['language' => 'Indonesia', 'speaking' => 'Baik', 'writing' => 'Baik'],
                ['language' => 'Inggris', 'speaking' => 'Menengah', 'writing' => 'Menengah'],
            ],
            'courses' => [
                ['name' => 'Leadership', 'organizer' => 'OMEO Academy', 'year' => '2025', 'certificate' => 'Ya'],
            ],
            'work_experiences' => [
                ['company' => 'PT Baru', 'position' => 'Supervisor', 'date_start' => '2021-02-01', 'date_end' => '2024-12-31', 'salary' => '5500000', 'reason' => 'Karir baru'],
            ],
            'reference_contacts' => [
                ['name' => 'Ref Baru', 'relation' => 'Manager', 'company' => 'PT Baru', 'phone' => '081233333333'],
            ],
            'organizations' => [
                ['name' => 'Karang Taruna', 'role' => 'Koordinator', 'year' => '2023'],
            ],
            'medical_histories' => [
                ['illness' => 'Typus', 'year' => '2021', 'hospitalized' => 'Ya', 'note' => 'Sembuh'],
            ],
            'social_medias' => [
                ['platform' => 'Instagram', 'handle' => '@probationbaru'],
            ],
            'sim_number' => 'SIM123456',
            'npwp_number' => 'NPWP123456',
            'bpjs_kes_number' => 'BPJSKES123',
            'bpjs_tk_number' => 'BPJSTK123',
            'passport_number' => 'PAS123',
            'kk_number' => 'KK999999',
        ];

        $this->actingAs($employeeUser)
            ->post(route('employee-profile.update'), $payload)
            ->assertRedirect(route('employee-profile.show'))
            ->assertSessionHas('success');

        $changeRequest = ProfileChangeRequest::query()->latest('id')->firstOrFail();
        $this->assertSame(ProfileChangeRequest::STATUS_PENDING, $changeRequest->status);
        $this->assertSame('Anak Kedua', data_get($changeRequest->changes_json, 'applicant_profile.family.1.name'));
        $this->assertSame('Ref Baru', data_get($changeRequest->changes_json, 'applicant_profile.reference_contacts.0.name'));

        $this->actingAs($hrd)
            ->post(route('hrd.probation-verifications.approve', $changeRequest))
            ->assertRedirect(route('hrd.probation-verifications.show', $changeRequest))
            ->assertSessionHas('success');

        $employee->refresh();
        $employeeUser->refresh();
        $changeRequest->refresh();
        $profile = ApplicantProfile::query()->where('user_id', $employeeUser->id)->firstOrFail();

        $this->assertSame('Probation Baru', $employee->full_name);
        $this->assertSame('baru@example.com', $employee->email_private);
        $this->assertSame('081299999999', $employee->phone_number);
        $this->assertSame('Probation Baru', $employeeUser->name);
        $this->assertSame('Bandung', $profile->place_of_birth);
        $this->assertSame('Alamat KTP Baru', $profile->ktp_address);
        $this->assertSame('Anak Kedua', data_get($profile->families, '1.name'));
        $this->assertSame('Universitas Baru', data_get($profile->educations, '0.school'));
        $this->assertSame('Ref Baru', data_get($profile->reference_contacts, '0.name'));
        $this->assertSame(ProfileChangeRequest::STATUS_APPROVED, $changeRequest->status);
    }

    public function test_employee_profile_uses_candidate_applicant_profile_fallback_when_direct_relation_is_missing(): void
    {
        $employee = Employee::query()->create([
            'nik' => '3175000000000014',
            'full_name' => 'Employee Legacy',
            'email_private' => 'legacy.employee@example.com',
            'phone_number' => null,
            'status_employment' => 'probation',
        ]);

        $employeeUser = User::factory()->create([
            'email' => 'legacy.employee@example.com',
            'role' => User::ROLE_PROBATION,
            'employee_id' => $employee->id,
        ]);

        $sourceUser = User::factory()->create([
            'email' => 'candidate-source@example.com',
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $sourceUser->id,
            'personal_json' => [
                'full_name' => 'Legacy Candidate Name',
                'email' => 'legacy.employee@example.com',
                'ktp_number' => '3175000000000014',
                'place_of_birth' => 'Yogyakarta',
                'date_of_birth' => '1999-09-09',
                'gender' => 'Perempuan',
                'religion' => 'Islam',
                'marital_status' => 'Single',
                'whatsapp' => '081211112222',
                'photo_path' => 'legacy/photo.jpg',
                'ktp_path' => 'legacy/ktp.pdf',
                'cv_path' => 'legacy/cv.pdf',
            ],
            'address_json' => [
                'ktp_address' => 'Jl. Kandidat Lama',
                'domicile_address' => 'Jl. Employee Legacy',
            ],
        ]);

        Candidate::query()->create([
            'full_name' => 'Legacy Candidate Name',
            'email' => 'legacy.employee@example.com',
            'nik' => '3175000000000014',
            'status' => Candidate::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        $this->actingAs($employeeUser)
            ->get(route('employee-profile.show'))
            ->assertOk()
            ->assertSeeText('Yogyakarta')
            ->assertSeeText('081211112222')
            ->assertSeeText('Jl. Kandidat Lama');
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
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
}







