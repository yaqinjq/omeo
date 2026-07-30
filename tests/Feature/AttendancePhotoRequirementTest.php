<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendancePhotoRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_check_in_requires_live_camera_payload_for_both_photos(): void
    {
        [$user, $outlet] = $this->seedAttendanceActor();

        $this->actingAs($user)
            ->from(route('attendance.index'))
            ->post(route('attendance.check-in'), [
                'outlet_id' => $outlet->id,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'accuracy' => 5,
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasErrors(['capture_mode', 'selfie_photo_data', 'environment_photo_data']);
    }

    public function test_attendance_rejects_manual_file_upload_mode(): void
    {
        Storage::fake('public');
        [$user, $outlet] = $this->seedAttendanceActor();

        $this->actingAs($user)
            ->post(route('attendance.check-in'), [
                'outlet_id' => $outlet->id,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'accuracy' => 5,
                'capture_mode' => 'file_fallback',
                'selfie_photo_data' => 'invalid',
                'environment_photo_data' => 'invalid',
            ])
            ->assertSessionHasErrors(['capture_mode']);
    }

    public function test_attendance_flow_enforces_state_and_stores_live_camera_evidence(): void
    {
        Storage::fake('public');
        [$user, $outlet] = $this->seedAttendanceActor();
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';

        $this->actingAs($user)
            ->post(route('attendance.check-in'), [
                'outlet_id' => $outlet->id,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'accuracy' => 5,
                'capture_mode' => 'live_camera',
                'selfie_photo_data' => $png,
                'environment_photo_data' => $png,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('attendance.check-in'), [
                'outlet_id' => $outlet->id,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'accuracy' => 5,
                'capture_mode' => 'live_camera',
                'selfie_photo_data' => $png,
                'environment_photo_data' => $png,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('attendance.check-out'), [
                'outlet_id' => $outlet->id,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'accuracy' => 5,
                'capture_mode' => 'live_camera',
                'selfie_photo_data' => $png,
                'environment_photo_data' => $png,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('attendance.check-out'), [
                'outlet_id' => $outlet->id,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'accuracy' => 5,
                'capture_mode' => 'live_camera',
                'selfie_photo_data' => $png,
                'environment_photo_data' => $png,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('attendance_scans', 2);
        $scans = \App\Models\AttendanceScan::query()->orderBy('id')->get();

        foreach ($scans as $scan) {
            $this->assertNotEmpty($scan->selfie_photo_path);
            $this->assertNotEmpty($scan->environment_photo_path);
            $this->assertSame('live_camera', data_get($scan->device_json, 'capture_mode'));
            Storage::disk('public')->assertExists($scan->selfie_photo_path);
            Storage::disk('public')->assertExists($scan->environment_photo_path);
        }
    }

    public function test_invalid_camera_payload_does_not_leave_partial_attendance_session(): void
    {
        Storage::fake('public');
        [$user, $outlet] = $this->seedAttendanceActor();

        $this->actingAs($user)
            ->from(route('attendance.index'))
            ->post(route('attendance.check-in'), [
                'outlet_id' => $outlet->id,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'accuracy' => 5,
                'capture_mode' => 'live_camera',
                'selfie_photo_data' => 'data:image/png;base64,not-valid',
                'environment_photo_data' => 'data:image/png;base64,not-valid',
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('attendance_sessions', 0);
        $this->assertDatabaseCount('attendance_scans', 0);
    }

    public function test_attendance_rejects_when_outlet_coordinates_are_missing(): void
    {
        Storage::fake('public');
        [$user, $outlet] = $this->seedAttendanceActor();
        $outlet->update([
            'latitude' => null,
            'longitude' => null,
        ]);
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';

        $this->actingAs($user)
            ->from(route('attendance.index'))
            ->post(route('attendance.check-in'), [
                'outlet_id' => $outlet->id,
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'accuracy' => 5,
                'capture_mode' => 'live_camera',
                'selfie_photo_data' => $png,
                'environment_photo_data' => $png,
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('attendance_scans', 0);
    }

    public function test_attendance_rejects_when_location_is_outside_geofence(): void
    {
        Storage::fake('public');
        [$user, $outlet] = $this->seedAttendanceActor();
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';

        $this->actingAs($user)
            ->from(route('attendance.index'))
            ->post(route('attendance.check-in'), [
                'outlet_id' => $outlet->id,
                'latitude' => -6.2100000,
                'longitude' => 106.8266660,
                'accuracy' => 5,
                'capture_mode' => 'live_camera',
                'selfie_photo_data' => $png,
                'environment_photo_data' => $png,
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('attendance_scans', 0);
    }

    public function test_attendance_is_locked_until_profile_and_payroll_are_complete(): void
    {
        Storage::fake('public');
        [$user, $outlet] = $this->seedAttendanceActor(completeProfile: false, completePayroll: false);
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';

        $this->actingAs($user)
            ->get(route('attendance.index'))
            ->assertSee('Presensi dikunci sementara');

        $this->actingAs($user)
            ->from(route('attendance.index'))
            ->post(route('attendance.check-in'), [
                'outlet_id' => $outlet->id,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'accuracy' => 5,
                'capture_mode' => 'live_camera',
                'selfie_photo_data' => $png,
                'environment_photo_data' => $png,
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('attendance_sessions', 0);
        $this->assertDatabaseCount('attendance_scans', 0);
    }

    private function seedAttendanceActor(bool $completeProfile = true, bool $completePayroll = true): array
    {
        $outlet = Outlet::query()->create([
            'name' => 'Outlet Test',
            'latitude' => -6.2000000,
            'longitude' => 106.8166660,
            'radius_meters' => 20,
            'timezone' => 'Asia/Jakarta',
            'work_start_time' => '08:00:00',
            'work_end_time' => '17:00:00',
        ]);

        $employee = Employee::query()->create([
            'nik' => '3175000000000015',
            'full_name' => 'Pegawai Presensi',
            'status_employment' => 'probation',
            'outlet_id' => $outlet->id,
            'sim_number' => $completePayroll ? 'SIM-001' : null,
            'sim_file_path' => $completePayroll ? 'payroll/sim.pdf' : null,
            'npwp_number' => $completePayroll ? 'NPWP-001' : null,
            'npwp_file_path' => $completePayroll ? 'payroll/npwp.pdf' : null,
            'bpjs_kes_number' => $completePayroll ? 'BPJS-KES-001' : null,
            'bpjs_kes_file_path' => $completePayroll ? 'payroll/bpjs-kes.pdf' : null,
            'kk_number' => $completePayroll ? 'KK-001' : null,
            'kk_file_path' => $completePayroll ? 'payroll/kk.pdf' : null,
            'payroll_verified_at' => $completePayroll ? now() : null,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_PROBATION,
            'employee_id' => $employee->id,
        ]);

        if (Schema::hasTable('employee_profiles')) {
            DB::table('employee_profiles')->insert([
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'sim_number' => $completePayroll ? 'SIM-001' : null,
                'sim_file_path' => $completePayroll ? 'payroll/sim.pdf' : null,
                'npwp_number' => $completePayroll ? 'NPWP-001' : null,
                'npwp_file_path' => $completePayroll ? 'payroll/npwp.pdf' : null,
                'bpjs_kes_number' => $completePayroll ? 'BPJS-KES-001' : null,
                'bpjs_kes_file_path' => $completePayroll ? 'payroll/bpjs-kes.pdf' : null,
                'kk_number' => $completePayroll ? 'KK-001' : null,
                'kk_file_path' => $completePayroll ? 'payroll/kk.pdf' : null,
                'payroll_verified_at' => $completePayroll ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

                if (Schema::hasTable('applicant_profiles')) {
            DB::table('applicant_profiles')->insert([
                'user_id' => $user->id,
                'personal_json' => json_encode($completeProfile ? [
                    'full_name' => 'Pegawai Presensi',
                    'ktp_number' => '3175000000000015',
                    'place_of_birth' => 'Jakarta',
                    'date_of_birth' => '2000-01-01',
                    'time_of_birth' => '08:15',
                    'gender' => 'Laki-laki',
                    'religion' => 'Islam',
                    'marital_status' => 'Single',
                    'whatsapp' => '081234567890',
                    'phone_number' => '081234567890',
                    'applied_position_name' => 'Crew Outlet',
                    'salary_expectation' => '5000000',
                    'willing_out_of_town' => 'Ya',
                    'willing_outside_java' => 'Ya',
                    'willing_shift' => 'Ya',
                    'willing_overtime' => 'Ya',
                    'is_smoker' => 'Tidak',
                    'has_computer_skill' => 'Ya',
                    'wears_glasses' => 'Tidak',
                    'join_reason' => 'Ingin berkembang bersama OMEO.',
                    'company_relation_note' => 'Tidak ada.',
                    'career_goal' => 'Menjadi leader operasional yang andal.',
                    'available_start_date' => now()->toDateString(),
                    'honesty_statement' => 'Saya menyatakan seluruh data yang saya isi adalah benar dan siap mempertanggungjawabkannya.',
                    'photo_path' => 'profiles/photo.jpg',
                    'ktp_path' => 'profiles/ktp.pdf',
                    'cv_path' => 'profiles/cv.pdf',
                    'signature_path' => 'profiles/signature.png',
                    'emergency_contacts' => [
                        ['name' => 'Ibu Darurat', 'relation' => 'Ibu', 'phone' => '081111111111', 'address' => 'Jakarta'],
                        ['name' => 'Kakak Darurat', 'relation' => 'Kakak', 'phone' => '082222222222', 'address' => 'Bekasi'],
                    ],
                    'reference_contacts' => [
                        ['name' => 'Ref 1', 'relation' => 'Supervisor', 'company' => 'PT Test', 'phone' => '081230000001'],
                        ['name' => 'Ref 2', 'relation' => 'Manager', 'company' => 'PT Test 2', 'phone' => '081230000002'],
                    ],
                ] : [
                    'full_name' => 'Pegawai Presensi',
                ]),
                'family_json' => json_encode($completeProfile ? [
                    ['relation' => 'Ayah', 'name' => 'Bapak', 'gender' => 'Laki-laki', 'dob' => '1970-01-01', 'education' => 'SMA', 'job' => 'Wiraswasta'],
                    ['relation' => 'Ibu', 'name' => 'Ibu', 'gender' => 'Perempuan', 'dob' => '1975-01-01', 'education' => 'SMA', 'job' => 'Ibu Rumah Tangga'],
                ] : []),
                'address_json' => json_encode($completeProfile ? [
                    'ktp_address' => 'Jl. Test No. 1',
                    'ktp_rt' => '001',
                    'ktp_rw' => '002',
                    'ktp_kelurahan' => 'Kelapa Gading',
                    'ktp_kecamatan' => 'Kelapa Gading',
                    'ktp_city' => 'Jakarta Utara',
                    'domicile_address' => 'Jl. Domisili No. 2',
                    'domicile_rt' => '003',
                    'domicile_rw' => '004',
                    'domicile_kelurahan' => 'Cempaka Putih',
                    'domicile_kecamatan' => 'Cempaka Putih',
                    'domicile_city' => 'Jakarta Pusat',
                    'ktp_province' => 'DKI Jakarta',
                ] : []),
                'education_json' => json_encode($completeProfile ? [
                    ['level' => 'SMP', 'school' => 'SMPN 1', 'major' => 'Umum', 'year_in' => '2012', 'year_out' => '2015'],
                    ['level' => 'SMA', 'school' => 'SMAN 1', 'major' => 'IPA', 'year_in' => '2015', 'year_out' => '2018'],
                    ['level' => 'D3', 'school' => 'Akademi Test', 'major' => 'Hospitality', 'year_in' => '2018', 'year_out' => '2021'],
                ] : []),
                'language_json' => json_encode($completeProfile ? [
                    ['language' => 'Indonesia', 'speaking' => 'Baik', 'writing' => 'Baik'],
                ] : []),
                'work_json' => json_encode($completeProfile ? [
                    ['company' => 'PT Lama', 'position' => 'Crew', 'date_start' => '2020-01-01', 'salary' => '4000000', 'reason' => 'Pindah'],
                ] : []),
                'course_json' => json_encode($completeProfile ? [
                    ['course_name' => 'Food Safety', 'organizer' => 'LKP', 'year' => '2021'],
                ] : []),
                'medical_json' => json_encode($completeProfile ? [
                    'histories' => [
                        ['illness' => 'Tidak ada', 'year' => '2024', 'hospitalized' => 'Tidak'],
                    ],
                    'weight_kg' => '65',
                    'height_cm' => '170',
                    'had_accident' => 'Tidak',
                    'police_record' => 'Tidak',
                    'psychology_test' => 'Tidak',
                ] : []),
                'social_json' => json_encode($completeProfile ? [
                    ['platform' => 'Instagram', 'handle' => '@pegawai'],
                ] : []),
                'organization_json' => json_encode([]),
                'completed_at' => $completeProfile ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return [$user, $outlet];
    }

    private function ensureSupportTables(): void
    {
        if (! Schema::hasTable('outlets')) {
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

        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('nik')->nullable();
                $table->string('full_name')->nullable();
                $table->string('status_employment')->nullable();
                $table->unsignedBigInteger('outlet_id')->nullable();
                $table->string('sim_number')->nullable();
                $table->string('sim_file_path')->nullable();
                $table->string('npwp_number')->nullable();
                $table->string('npwp_file_path')->nullable();
                $table->string('bpjs_kes_number')->nullable();
                $table->string('bpjs_kes_file_path')->nullable();
                $table->string('kk_number')->nullable();
                $table->string('kk_file_path')->nullable();
                $table->timestamp('payroll_verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        foreach ([
            'sim_number' => fn (Blueprint $table) => $table->string('sim_number')->nullable(),
            'sim_file_path' => fn (Blueprint $table) => $table->string('sim_file_path')->nullable(),
            'npwp_number' => fn (Blueprint $table) => $table->string('npwp_number')->nullable(),
            'npwp_file_path' => fn (Blueprint $table) => $table->string('npwp_file_path')->nullable(),
            'bpjs_kes_number' => fn (Blueprint $table) => $table->string('bpjs_kes_number')->nullable(),
            'bpjs_kes_file_path' => fn (Blueprint $table) => $table->string('bpjs_kes_file_path')->nullable(),
            'kk_number' => fn (Blueprint $table) => $table->string('kk_number')->nullable(),
            'kk_file_path' => fn (Blueprint $table) => $table->string('kk_file_path')->nullable(),
            'payroll_verified_at' => fn (Blueprint $table) => $table->timestamp('payroll_verified_at')->nullable(),
        ] as $column => $callback) {
            if (! Schema::hasColumn('employees', $column)) {
                Schema::table('employees', $callback);
            }
        }

        if (! Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('sim_number')->nullable();
                $table->string('sim_file_path')->nullable();
                $table->string('npwp_number')->nullable();
                $table->string('npwp_file_path')->nullable();
                $table->string('bpjs_kes_number')->nullable();
                $table->string('bpjs_kes_file_path')->nullable();
                $table->string('kk_number')->nullable();
                $table->string('kk_file_path')->nullable();
                $table->timestamp('payroll_verified_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('applicant_profiles')) {
            Schema::create('applicant_profiles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->json('personal_json')->nullable();
                $table->json('family_json')->nullable();
                $table->json('address_json')->nullable();
                $table->json('education_json')->nullable();
                $table->json('language_json')->nullable();
                $table->json('work_json')->nullable();
                $table->json('organization_json')->nullable();
                $table->json('course_json')->nullable();
                $table->json('medical_json')->nullable();
                $table->json('social_json')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }
}



