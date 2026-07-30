<?php

namespace Tests\Feature;

use App\Exports\EmployeesExport;
use App\Models\Employee;
use App\Models\EmployeeSalaryHistory;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class EmployeeMaster360Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_employee_index_supports_view_all_filters_and_export(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);

        $department = \App\Models\Department::query()->create(['name' => 'Operasional']);
        $position = \App\Models\Position::query()->create(['name' => 'Captain']);
        $outlet = \App\Models\Outlet::query()->create(['name' => 'Outlet Sudirman', 'brand_name' => 'OMEO Coffee']);

        $employee = Employee::query()->create([
            'nik' => '3175000000000901',
            'employee_number' => 'EMP-901',
            'full_name' => 'Nadia Operasional',
            'email_private' => 'nadia@example.com',
            'phone_number' => '081234567890',
            'join_date' => '2026-01-10',
            'status_employment' => 'probation',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'outlet_id' => $outlet->id,
            'current_salary' => 5500000,
        ]);
        $employee->forceFill([
            'bpjs_kes_number' => 'KES-901',
            'bpjs_tk_number' => 'TK-901',
            'kk_number' => 'KK-901',
        ])->save();

        $user = User::factory()->create([
            'role' => User::ROLE_PROBATION,
            'employee_id' => $employee->id,
            'email' => 'nadia.login@example.com',
        ]);

        \App\Models\ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Nadia Sesuai KTP',
                'ktp_number' => '3175000000000901',
                'place_of_birth' => 'Jakarta',
                'date_of_birth' => '1999-03-12',
                'reference_contacts' => [
                    ['name' => 'Referensi Nadia', 'relation' => 'Atasan', 'company' => 'PT Lama', 'phone' => '081233344400'],
                ],
                'emergency_contacts' => [
                    ['name' => 'Darurat Nadia', 'relation' => 'Ibu', 'phone' => '081233344411', 'address' => 'Jakarta'],
                ],
            ],
            'address_json' => [
                'ktp_address' => 'Jl. KTP Nadia',
                'domicile_address' => 'Jl. Domisili Nadia',
            ],
            'family_json' => [
                ['kk_no' => 'KK-FAM-901', 'nik' => '3175000000000911', 'relation' => 'Ibu', 'name' => 'Ibu Nadia', 'gender' => 'Perempuan', 'job' => 'Wiraswasta'],
            ],
            'education_json' => [['school' => 'SMK Pariwisata 1', 'major' => 'Perhotelan', 'year_out' => 2024]],
            'language_json' => [['language' => 'Inggris', 'speaking' => 'Baik', 'writing' => 'Baik']],
            'course_json' => [['name' => 'Barista Dasar', 'organizer' => 'Coffee Academy', 'year' => '2025', 'certificate' => 'Ada']],
            'work_json' => [['company' => 'PT Lama', 'position' => 'Crew', 'date_start' => '2024-01-01', 'salary' => '4500000', 'reason' => 'Kontrak selesai']],
            'medical_json' => ['histories' => [['illness' => 'Tidak ada', 'year' => '2026', 'hospitalized' => 'Tidak', 'note' => 'Sehat']]],
            'social_json' => [['platform' => 'Instagram', 'handle' => '@nadia.ops']],
        ]);

        \App\Models\EmployeeBankAccount::query()->create([
            'employee_id' => $employee->id,
            'bank_code' => 'bca',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Nadia Operasional',
            'is_primary' => true,
        ]);

        $this->actingAs($hrd)
            ->get(route('employees.index', [
                'view' => 'all',
                'brand_name' => 'OMEO Coffee',
                'latest_school' => 'SMK Pariwisata',
                'status' => 'probation',
            ]))
            ->assertOk()
            ->assertSeeText('View All Master Karyawan')
            ->assertSeeText('Nadia Operasional')
            ->assertSeeText('SMK Pariwisata 1 - Perhotelan');

        Carbon::setTestNow('2026-03-17 10:00:00');
        Excel::fake();

        $this->actingAs($hrd)
            ->get(route('employees.export', [
                'brand_name' => 'OMEO Coffee',
                'latest_school' => 'SMK Pariwisata',
            ]))
            ->assertOk();

        Excel::assertDownloaded('master-karyawan-20260317-100000.xlsx', function (EmployeesExport $export): bool {
            $headings = $export->headings();
            $row = $export->collection()->first();
            $cell = function (string $heading) use ($headings, $row) {
                $index = array_search($heading, $headings, true);
                $this->assertNotFalse($index, "Heading {$heading} tidak ditemukan di export karyawan.");

                return $row[$index] ?? null;
            };

            $this->assertSame('3175000000000901', $cell('No. KTP'));
            $this->assertSame('KK-901', $cell('KK'));
            $this->assertSame('KES-901', $cell('BPJS Kesehatan'));
            $this->assertSame('TK-901', $cell('BPJS Ketenagakerjaan'));
            $this->assertSame('KK-FAM-901', $cell('Keluarga 1 - No. KK'));
            $this->assertSame('Ibu Nadia', $cell('Keluarga 1 - Nama'));
            $this->assertSame('Darurat Nadia', $cell('Kontak Darurat 1 - Nama'));
            $this->assertSame('SMK Pariwisata 1', $cell('Pendidikan 1 - Sekolah/Kampus'));
            $this->assertSame('Inggris', $cell('Bahasa 1 - Bahasa'));
            $this->assertSame('Barista Dasar', $cell('Kursus 1 - Nama Kursus'));
            $this->assertSame('PT Lama', $cell('Riwayat Kerja 1 - Perusahaan'));
            $this->assertSame('Referensi Nadia', $cell('Referensi 1 - Nama'));
            $this->assertSame('Tidak ada', $cell('Riwayat Medis 1 - Penyakit'));
            $this->assertSame('@nadia.ops', $cell('Social Media 1 - Username/Link'));

            return true;
        });
        Carbon::setTestNow();
    }

    public function test_employee_profile_shows_salary_appraisal_training_and_profile_change_history(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $department = \App\Models\Department::query()->create(['name' => 'HR']);
        $position = \App\Models\Position::query()->create(['name' => 'HR Officer']);
        $outlet = \App\Models\Outlet::query()->create(['name' => 'Outlet Tebet', 'brand_name' => 'OMEO Eatery']);

        $employee = Employee::query()->create([
            'nik' => '3175000000000902',
            'employee_number' => 'EMP-902',
            'full_name' => 'Rina HR',
            'email_private' => 'rina@example.com',
            'phone_number' => '081211112222',
            'join_date' => '2025-08-01',
            'probation_end_date' => '2025-11-01',
            'status_employment' => 'permanent',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'outlet_id' => $outlet->id,
            'current_salary' => 7200000,
            'npwp_number' => '09.999.888.1-123.000',
            'bpjs_kes_number' => 'KES-001',
            'bpjs_tk_number' => 'TK-001',
            'sim_number' => 'SIM-C-001',
            'kk_number' => 'KK-001',
            'payroll_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'employee_id' => $employee->id,
            'email' => 'rina.login@example.com',
        ]);

        \App\Models\ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'education_json' => [['school' => 'Universitas HR', 'major' => 'Psikologi', 'year_out' => 2023]],
            'personal_json' => ['salary_expectation' => 9000000],
        ]);

        \App\Models\EmployeeBankAccount::query()->create([
            'employee_id' => $employee->id,
            'bank_code' => 'mandiri',
            'bank_name' => 'Bank Mandiri',
            'account_number' => '9988776655',
            'account_holder_name' => 'Rina HR',
            'is_primary' => true,
        ]);

        EmployeeSalaryHistory::query()->create([
            'employee_id' => $employee->id,
            'amount' => 6500000,
            'effective_date' => '2025-08-01',
            'changed_by' => $hrd->id,
            'source' => 'employee_master',
            'notes' => 'Gaji awal onboarding',
        ]);

        EmployeeSalaryHistory::query()->create([
            'employee_id' => $employee->id,
            'amount' => 7200000,
            'effective_date' => '2026-01-01',
            'changed_by' => $hrd->id,
            'source' => 'employee_master',
            'notes' => 'Penyesuaian appraisal',
        ]);

        $periodId = \Illuminate\Support\Facades\DB::table('appraisal_periods')->insertGetId([
            'name' => 'Q1 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = \Illuminate\Support\Facades\DB::table('appraisal_indicators')->insertGetId([
            'name' => 'Leadership',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appraisalId = \Illuminate\Support\Facades\DB::table('appraisals')->insertGetId([
            'appraisal_period_id' => $periodId,
            'employee_id' => $employee->id,
            'appraiser_id' => $hrd->id,
            'date_appraised' => '2026-03-10',
            'final_score' => 88.5,
            'final_result' => 'Excellent',
            'notes_hrd' => 'Pertahankan konsistensi tim.',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('appraisal_details')->insert([
            'appraisal_id' => $appraisalId,
            'appraisal_indicator_id' => $indicatorId,
            'score' => 90,
            'comment' => 'Komunikasi dengan tim sangat baik.',
        ]);

        $materialId = \Illuminate\Support\Facades\DB::table('training_materials')->insertGetId([
            'title' => 'Leadership Basics',
            'description' => 'Leadership training',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('training_participations')->insert([
            'employee_id' => $employee->id,
            'training_material_id' => $materialId,
            'status' => 'completed',
            'completion_date' => now(),
            'quiz_score' => 95,
            'is_refreshment' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ProfileChangeRequest::query()->create([
            'user_id' => $user->id,
            'entity_type' => ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE,
            'changes_json' => ['profile' => ['phone_number' => '081299999999']],
            'attachments_json' => [],
            'status' => ProfileChangeRequest::STATUS_APPROVED,
            'submitted_at' => now()->subDays(2),
            'reviewed_by' => $hrd->id,
            'reviewed_at' => now()->subDay(),
            'review_note' => 'Disetujui oleh HRD.',
        ]);

        $this->actingAs($hrd)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSeeText('Profile 360')
            ->assertSeeText('Rina HR')
            ->assertSeeText('Gaji Saat Ini')
            ->assertSeeText('Penyesuaian appraisal')
            ->assertSeeText('Leadership Basics')
            ->assertSeeText('Komunikasi dengan tim sangat baik.')
            ->assertDontSeeText('9000000');
    }

    public function test_employee_profile_uses_candidate_sections_and_documents_in_hrd_detail(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $department = \App\Models\Department::query()->create(['name' => 'People Ops']);
        $position = \App\Models\Position::query()->create(['name' => 'HR Generalist']);
        $outlet = \App\Models\Outlet::query()->create(['name' => 'Outlet Kemang', 'brand_name' => 'OMEO Coffee']);

        $employee = Employee::query()->create([
            'nik' => '3175000000000904',
            'employee_number' => 'EMP-904',
            'full_name' => 'Rina Employee Master',
            'email_private' => null,
            'phone_number' => null,
            'join_date' => '2025-07-01',
            'status_employment' => 'permanent',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'outlet_id' => $outlet->id,
            'current_salary' => 8300000,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'employee_id' => $employee->id,
            'email' => 'rina.employee@example.com',
        ]);

        \App\Models\ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Rina Kandidat Lama',
                'email' => 'rina.kandidat@example.com',
                'ktp_number' => '3175000000000904',
                'whatsapp' => '081233344455',
                'place_of_birth' => 'Bandung',
                'date_of_birth' => '1998-04-17',
                'gender' => 'Perempuan',
                'religion' => 'Islam',
                'blood_type' => 'O',
                'marital_status' => 'Menikah',
                'marriage_date' => '2024-01-14',
                'photo_path' => 'profiles/rina-photo.jpg',
                'ktp_path' => 'documents/rina-ktp.pdf',
                'cv_path' => 'documents/rina-cv.pdf',
                'reference_contacts' => [
                    ['name' => 'Ibu Referensi', 'relation' => 'Mantan Atasan', 'company' => 'PT Kandidat Hebat', 'phone' => '081377788899'],
                ],
            ],
            'address_json' => [
                'ktp_address' => 'Jl. Profil Kandidat 12',
                'ktp_province' => 'Jawa Barat',
                'ktp_city' => 'Bandung',
                'domicile_address' => 'Jl. Domisili Rina 34',
            ],
            'family_json' => [
                ['relation' => 'Suami', 'name' => 'Budi', 'gender' => 'Laki-laki', 'dob' => '1995-01-01', 'education' => 'S1', 'job' => 'Karyawan'],
            ],
            'education_json' => [
                ['level' => 'S1', 'school' => 'Universitas Kandidat', 'major' => 'Psikologi', 'year_in' => '2016', 'year_out' => '2020', 'gpa' => '3.72'],
            ],
            'language_json' => [
                ['language' => 'Inggris', 'speaking' => 'Baik', 'writing' => 'Baik'],
            ],
            'course_json' => [
                ['name' => 'Certified Recruiter', 'organizer' => 'HR Academy', 'year' => '2021', 'certificate' => 'Ada'],
            ],
            'work_json' => [
                ['company' => 'PT Kandidat Hebat', 'position' => 'Supervisor Lama', 'date_start' => '2020-01-01', 'date_end' => '2025-06-01', 'salary' => '7000000', 'reason' => 'Pindah karier'],
            ],
            'organization_json' => [
                ['name' => 'Komunitas HR Bandung', 'role' => 'Koordinator', 'year' => '2019'],
            ],
            'medical_json' => [
                ['illness' => 'Tipes', 'year' => '2022', 'hospitalized' => 'Ya', 'note' => 'Sembuh total'],
            ],
            'social_json' => [
                ['platform' => 'Instagram', 'handle' => '@rina.employee'],
            ],
        ]);

        $this->actingAs($hrd)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSeeText('Profile 360')
            ->assertSeeText('Rina Employee Master')
            ->assertDontSeeText('Rina Kandidat Lama')
            ->assertSeeText('Informasi Dasar')
            ->assertSeeText('Keluarga')
            ->assertSeeText('Universitas Kandidat')
            ->assertSeeText('PT Kandidat Hebat')
            ->assertSeeText('Ibu Referensi')
            ->assertSeeText('Komunitas HR Bandung')
            ->assertSeeText('Tipes')
            ->assertSeeText('@rina.employee')
            ->assertSeeText('Buka File')
            ->assertSeeText('Gaji Saat Ini');
    }

    public function test_employee_update_records_salary_history(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $position = \App\Models\Position::query()->create(['name' => 'Supervisor']);

        $employee = Employee::query()->create([
            'nik' => '3175000000000903',
            'employee_number' => 'EMP-903',
            'full_name' => 'Dian Supervisor',
            'status_employment' => 'contract',
            'position_id' => $position->id,
            'current_salary' => 6000000,
        ]);

        $this->actingAs($hrd)
            ->put(route('employees.update', $employee), [
                'nik' => '3175000000000903',
                'employee_number' => 'EMP-903',
                'full_name' => 'Dian Supervisor',
                'status_employment' => 'contract',
                'position_id' => $position->id,
                'current_salary' => 6500000,
                'salary_effective_date' => '2026-03-17',
                'salary_notes' => 'Penyesuaian tahunan',
            ])
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('success');

        $employee->refresh();
        $this->assertSame('6500000.00', number_format((float) $employee->current_salary, 2, '.', ''));
        $this->assertDatabaseHas('employee_salary_histories', [
            'employee_id' => $employee->id,
            'amount' => 6500000,
            'notes' => 'Penyesuaian tahunan',
        ]);
    }

    private function ensureSupportTables(): void
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('departments', 'deleted_at')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('positions', 'deleted_at')) {
            Schema::table('positions', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('outlets')) {
            Schema::create('outlets', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('brand_name')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('outlets', function (Blueprint $table): void {
                if (!Schema::hasColumn('outlets', 'brand_name')) {
                    $table->string('brand_name')->nullable();
                }

                if (!Schema::hasColumn('outlets', 'deleted_at')) {
                    $table->softDeletes();
                }
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
                $table->decimal('current_salary', 15, 2)->nullable();
                $table->string('status_employment')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('outlet_id')->nullable();
                $table->string('jabatan')->nullable();
                $table->string('npwp_number')->nullable();
                $table->string('bpjs_kes_number')->nullable();
                $table->string('bpjs_tk_number')->nullable();
                $table->string('sim_number')->nullable();
                $table->string('kk_number')->nullable();
                $table->timestamp('payroll_verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('applicant_profiles')) {
            Schema::create('applicant_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable();
                $table->json('personal_json')->nullable();
                $table->json('education_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employee_bank_accounts')) {
            Schema::create('employee_bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id');
                $table->string('bank_code')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('account_holder_name')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employee_salary_histories')) {
            Schema::create('employee_salary_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id');
                $table->decimal('amount', 15, 2);
                $table->date('effective_date')->nullable();
                $table->foreignId('changed_by')->nullable();
                $table->string('source')->nullable();
                $table->text('notes')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('profile_change_requests')) {
            Schema::create('profile_change_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable();
                $table->string('entity_type')->nullable();
                $table->json('changes_json')->nullable();
                $table->json('attachments_json')->nullable();
                $table->string('status')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisal_periods')) {
            Schema::create('appraisal_periods', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisal_indicators')) {
            Schema::create('appraisal_indicators', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisals')) {
            Schema::create('appraisals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('appraisal_period_id')->nullable();
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('appraiser_id')->nullable();
                $table->date('date_appraised')->nullable();
                $table->decimal('final_score', 8, 2)->nullable();
                $table->string('final_result')->nullable();
                $table->text('notes_hrd')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisal_details')) {
            Schema::create('appraisal_details', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('appraisal_id')->nullable();
                $table->unsignedBigInteger('appraisal_indicator_id')->nullable();
                $table->decimal('score', 8, 2)->nullable();
                $table->text('comment')->nullable();
            });
        }

        if (!Schema::hasTable('training_materials')) {
            Schema::create('training_materials', function (Blueprint $table): void {
                $table->id();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('training_participations')) {
            Schema::create('training_participations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('training_material_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamp('completion_date')->nullable();
                $table->decimal('quiz_score', 8, 2)->nullable();
                $table->boolean('is_refreshment')->default(false);
                $table->timestamps();
            });
        }
    }
}





