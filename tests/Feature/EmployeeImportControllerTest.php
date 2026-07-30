<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_download_template_is_available(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);

        $this->actingAs($hrd)
            ->get(route('employees.import.template'))
            ->assertOk();
    }

    public function test_import_creates_employee_and_links_existing_user(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Department::query()->create(['code' => 'HRD', 'name' => 'Human Resource']);
        $position = Position::query()->create(['name' => 'Crew Outlet', 'level' => 1]);
        $outlet = Outlet::query()->create([
            'name' => 'Outlet Darmo',
            'brand_name' => 'MEO',
            'external_id' => 'OUTLET-001',
            'timezone' => 'Asia/Jakarta',
        ]);
        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'role' => User::ROLE_CANDIDATE,
            'employee_id' => null,
        ]);

        $file = UploadedFile::fake()->createWithContent('employees.csv', implode("\n", [
            'employee_number,nik,full_name,email_private,phone_number,status_employment,join_date,probation_end_date,department_code,department_name,position_name,outlet_external_id,outlet_name,brand_name,external_id,current_salary',
            'EMP-0001,3578123412341234,Budi Santoso,budi@example.com,08123456789,probation,2026-03-01,2026-06-01,HRD,Human Resource,Crew Outlet,OUTLET-001,Outlet Darmo,MEO,MEO-123,4500000',
        ]));

        $this->actingAs($hrd)
            ->post(route('employees.import.store'), ['file' => $file])
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('employee_import_summary');

        $employee = Employee::query()->firstOrFail();
        $user->refresh();

        $this->assertSame('3578123412341234', $employee->nik);
        $this->assertSame('EMP-0001', $employee->employee_number);
        $this->assertSame('Budi Santoso', $employee->full_name);
        $this->assertSame('probation', $employee->status_employment);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame($outlet->id, $employee->outlet_id);
        $this->assertSame($employee->id, $user->employee_id);
        $this->assertSame(User::ROLE_PROBATION, $user->role);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_import_accepts_alias_headers_and_warns_for_unknown_columns(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Department::query()->create(['code' => 'HRD', 'name' => 'Human Resource']);
        Position::query()->create(['name' => 'Crew Outlet', 'level' => 1]);

        $file = UploadedFile::fake()->createWithContent('employees-flex.csv', implode("\n", [
            "\xEF\xBB\xBFemployee number,nik,nama lengkap,email private,status,department code,jabatan,unknown header",
            'EMP-0001,3578123412341234,Budi Santoso,budi@example.com,probation,HRD,Crew Outlet,ignored',
        ]));

        $response = $this->actingAs($hrd)
            ->post(route('employees.import.store'), ['file' => $file]);

        $response->assertRedirect(route('employees.index'))
            ->assertSessionHas('employee_import_summary.created', 1)
            ->assertSessionHas('employee_import_summary.failed', 0)
            ->assertSessionHas('employee_import_summary.warnings');

        $warnings = collect(session('employee_import_summary.warnings', []));
        $this->assertTrue($warnings->contains(fn ($warning) => str_contains($warning, 'unknown header')));
        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-0001',
            'nik' => '3578123412341234',
            'full_name' => 'Budi Santoso',
        ]);
    }

    public function test_import_skips_duplicate_employee_rows_safely(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Department::query()->create(['code' => 'HRD', 'name' => 'Human Resource']);
        $position = Position::query()->create(['name' => 'Crew Outlet', 'level' => 1]);

        Employee::query()->create([
            'nik' => '3578123412341234',
            'employee_number' => 'EMP-0001',
            'full_name' => 'Existing Employee',
            'status_employment' => 'probation',
            'position_id' => $position->id,
        ]);

        $file = UploadedFile::fake()->createWithContent('employees-duplicate.csv', implode("\n", [
            'employee_number,nik,full_name,email_private,phone_number,status_employment,join_date,probation_end_date,department_code,department_name,position_name,outlet_external_id,outlet_name,brand_name,external_id,current_salary',
            'EMP-0001,3578123412341234,Budi Santoso,budi@example.com,08123456789,probation,2026-03-01,2026-06-01,HRD,Human Resource,Crew Outlet,,,,4500000',
        ]));

        $response = $this->actingAs($hrd)
            ->post(route('employees.import.store'), ['file' => $file]);

        $response->assertRedirect(route('employees.index'))
            ->assertSessionHas('warning')
            ->assertSessionHas('employee_import_summary');

        $summary = session('employee_import_summary');
        $this->assertSame(0, data_get($summary, 'created'));
        $this->assertSame(1, data_get($summary, 'duplicates'));
        $this->assertDatabaseCount('employees', 1);
    }

    private function ensureSupportTables(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('level')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('outlets')) {
            Schema::create('outlets', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('brand_name')->nullable();
                $table->string('external_id')->nullable();
                $table->string('location')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('radius_meters')->nullable();
                $table->unsignedInteger('geofence_radius_m')->nullable();
                $table->string('timezone')->nullable();
                $table->string('work_start_time')->nullable();
                $table->string('work_end_time')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('employees')) {
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
                $table->string('nokom')->nullable();
                $table->string('jabatan')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('employee_salary_histories')) {
            Schema::create('employee_salary_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id');
                $table->decimal('amount', 15, 2);
                $table->date('effective_date')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->string('source')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
}
