<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeIndexResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_index_stays_accessible_when_employee_table_is_minimal(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('employees');
        Schema::enableForeignKeyConstraints();

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('nik')->nullable();
            $table->string('full_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);

        Employee::query()->create([
            'nik' => '3175000000000101',
            'full_name' => 'Employee Minimal',
        ]);

        $this->actingAs($hrd)
            ->get(route('employees.index', [
                'q' => 'Employee',
                'status' => 'probation',
                'join_date_from' => '2026-01-01',
                'view' => 'all',
            ]))
            ->assertOk()
            ->assertSeeText('Master Karyawan')
            ->assertSeeText('Employee Minimal');
    }
}
