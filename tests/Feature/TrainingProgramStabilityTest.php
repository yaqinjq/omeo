<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrainingProgramStabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_program_index_stays_safe_when_lms_tables_are_missing(): void
    {
        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        Schema::dropIfExists('training_program_enrollments');
        Schema::dropIfExists('training_program_material');
        Schema::dropIfExists('training_programs');

        $this->actingAs($hrd)
            ->get(route('training-programs.index'))
            ->assertOk()
            ->assertSeeText('Modul Training Programs belum siap di environment ini');
    }

    public function test_my_training_stays_safe_when_event_tables_are_missing(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('full_name');
                $table->string('status_employment')->default('probation');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $employeeId = \App\Models\Employee::query()->create([
            'full_name' => 'Karyawan Training',
            'status_employment' => 'probation',
        ])->id;

        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYEE,
            'employee_id' => $employeeId,
        ]);

        Schema::dropIfExists('training_event_participants');
        Schema::dropIfExists('training_events');
        Schema::dropIfExists('training_trainers');

        $this->actingAs($user)
            ->get(route('my-training.index'))
            ->assertOk()
            ->assertSeeText('My Training');
    }

    public function test_my_training_is_available_for_all_core_roles(): void
    {
        foreach (User::ALLOWED_ROLES as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'employee_id' => null,
            ]);

            $this->actingAs($user)
                ->get(route('my-training.index'))
                ->assertOk()
                ->assertSeeText('My Training');
        }
    }
}
