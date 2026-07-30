<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeProfileCandidateFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_employee_profile_uses_applicant_profile_name_and_email_when_employee_master_is_sparse(): void
    {
        $employee = Employee::query()->create([
            'nik' => '3175000000000015',
            'full_name' => '',
            'email_private' => null,
            'phone_number' => null,
            'status_employment' => 'probation',
        ]);

        $employeeUser = User::factory()->create([
            'email' => 'legacy.profile@example.com',
            'name' => '',
            'role' => User::ROLE_PROBATION,
            'employee_id' => $employee->id,
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $employeeUser->id,
            'personal_json' => [
                'full_name' => 'Nama Kandidat Lama',
                'email' => 'kontak.pribadi@example.com',
                'ktp_number' => '3175000000000015',
                'whatsapp' => '081288887777',
                'place_of_birth' => 'Semarang',
            ],
            'address_json' => [
                'ktp_address' => 'Jl. Profil Lama 123',
            ],
        ]);

        $this->actingAs($employeeUser)
            ->get(route('employee-profile.show'))
            ->assertOk()
            ->assertSeeText('Nama Kandidat Lama')
            ->assertSeeText('kontak.pribadi@example.com')
            ->assertSeeText('081288887777')
            ->assertSeeText('Semarang');
    }

    public function test_accepting_candidate_keeps_applicant_profile_visible_in_my_profile(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidateUser = User::factory()->create([
            'email' => 'accept.profile@example.com',
            'name' => 'Login Kandidat',
            'role' => User::ROLE_CANDIDATE,
            'employee_id' => null,
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $candidateUser->id,
            'personal_json' => [
                'full_name' => 'Kandidat Jadi Karyawan',
                'email' => 'accept.profile@example.com',
                'ktp_number' => '3175000000000016',
                'whatsapp' => '081277776666',
                'place_of_birth' => 'Solo',
                'photo_path' => 'profiles/accept-photo.jpg',
            ],
            'address_json' => [
                'domicile_address' => 'Jl. Kandidat Diterima',
            ],
        ]);

        $candidate = Candidate::query()->create([
            'full_name' => 'Kandidat Jadi Karyawan',
            'email' => 'accept.profile@example.com',
            'nik' => '3175000000000016',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $this->actingAs($hrd)
            ->post(route('candidates.accept', $candidate))
            ->assertSessionHas('success');

        $candidateUser->refresh();
        $candidate->refresh();

        $this->assertNotNull($candidateUser->employee_id);
        $this->assertSame($candidateUser->id, $candidate->user_id);

        $this->actingAs($candidateUser)
            ->get(route('employee-profile.show'))
            ->assertOk()
            ->assertSeeText('Kandidat Jadi Karyawan')
            ->assertSeeText('081277776666')
            ->assertSeeText('Solo')
            ->assertSeeText('Jl. Kandidat Diterima');
    }

    public function test_employee_profile_renders_rich_candidate_sections_and_documents(): void
    {
        $employee = Employee::query()->create([
            'nik' => '3175000000000017',
            'full_name' => '',
            'email_private' => null,
            'phone_number' => null,
            'status_employment' => 'probation',
        ]);

        $employeeUser = User::factory()->create([
            'email' => 'rich.profile@example.com',
            'name' => '',
            'role' => User::ROLE_PROBATION,
            'employee_id' => $employee->id,
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $employeeUser->id,
            'personal_json' => [
                'full_name' => 'Kandidat Dengan Data Lengkap',
                'email' => 'rich.profile@example.com',
                'ktp_number' => '3175000000000017',
                'whatsapp' => '081233344455',
                'photo_path' => 'profiles/rich-photo.jpg',
                'ktp_path' => 'documents/rich-ktp.pdf',
                'cv_path' => 'documents/rich-cv.pdf',
            ],
            'family_json' => [
                ['relation' => 'Ayah', 'name' => 'Orang Tua Kandidat', 'job' => 'Wiraswasta'],
            ],
            'education_json' => [
                ['level' => 'S1', 'school' => 'Universitas Kandidat', 'major' => 'Manajemen', 'year_in' => '2015', 'year_out' => '2019'],
            ],
            'work_json' => [
                ['company_name' => 'PT Kandidat Hebat', 'position' => 'Supervisor'],
            ],
            'organization_json' => [
                ['name' => 'Komunitas HR', 'role' => 'Koordinator', 'year' => '2018'],
            ],
            'social_json' => [
                ['platform' => 'Instagram', 'account' => '@kandidatlengkap'],
            ],
        ]);

        $this->actingAs($employeeUser)
            ->get(route('employee-profile.show'))
            ->assertOk()
            ->assertSeeText('Kandidat Dengan Data Lengkap')
            ->assertSeeText('Keluarga')
            ->assertSeeText('Orang Tua Kandidat')
            ->assertSeeText('Universitas Kandidat')
            ->assertSeeText('PT Kandidat Hebat')
            ->assertSeeText('Komunitas HR')
            ->assertSeeText('@kandidatlengkap')
            ->assertSeeText('Buka File');
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
                $table->string('external_id')->nullable();
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



