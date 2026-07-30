<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardStabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_hrd_dashboard_stays_accessible_even_without_employees_table(): void
    {
        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        $this->actingAs($hrd)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('HR Dashboard');
    }

    public function test_probation_dashboard_uses_probation_portal_experience(): void
    {
        $employee = Employee::query()->create([
            'full_name' => 'Probation User',
            'status_employment' => 'probation',
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_PROBATION,
            'employee_id' => $employee->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Portal Probation')
            ->assertSeeText('My Profile')
            ->assertSeeText('Presensi saya');
    }


    public function test_manager_sidebar_hides_master_data_menu_without_permission(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
        ]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Master Departemen')
            ->assertDontSeeText('Master Posisi')
            ->assertDontSeeText('Master Outlet');
    }

    public function test_hrd_sidebar_shows_master_data_menu_when_permitted(): void
    {
        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        $this->actingAs($hrd)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Master Departemen')
            ->assertSeeText('Master Posisi')
            ->assertSeeText('Master Outlet');
    }    private function ensureSupportTables(): void
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
