<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppraisalWorkflowReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_hrd_assignment_page_loads_with_clear_workflow_labels(): void
    {
        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        DB::table('appraisal_periods')->insert([
            'name' => 'Probation April',
            'type' => 'probation',
            'start_date' => now()->subDays(1)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrd)
            ->get(route('appraisals.assignment'))
            ->assertOk()
            ->assertSeeText('Assignment Appraisal')
            ->assertSeeText('Periode Appraisal')
            ->assertSeeText('Indikator Appraisal')
            ->assertSeeText('Semua Appraisal');
    }
    public function test_hrd_can_generate_assignment_with_due_date_and_send_reminder(): void
    {
        $hrd = User::factory()->create([
            'role' => User::ROLE_HRD,
        ]);

        $appraiser = User::factory()->create([
            'role' => User::ROLE_MANAGER,
        ]);

        $employee = Employee::query()->create([
            'nik' => '3175000000000018',
            'full_name' => 'Probation Appraisal',
            'status_employment' => 'probation',
            'probation_end_date' => now()->addDays(7)->toDateString(),
        ]);

        $periodId = DB::table('appraisal_periods')->insertGetId([
            'name' => 'Probation Maret',
            'type' => 'probation',
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrd)
            ->post(route('appraisals.assignment.generate'), [
                'appraisal_period_id' => $periodId,
                'employee_ids' => [$employee->id],
                'appraiser_user_ids' => [$appraiser->id],
                'date_appraised' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'invitation_note' => 'Mohon fokus pada konsistensi SOP outlet.',
                'is_feedback_private' => '1',
            ])
            ->assertRedirect(route('appraisals.assignment'))
            ->assertSessionHas('success');

        $appraisal = DB::table('appraisals')->first();
        $this->assertNotNull($appraisal);
        $this->assertSame((string) $appraiser->id, (string) $appraisal->appraiser_id);
        $this->assertStringStartsWith(now()->addDays(5)->toDateString(), (string) $appraisal->due_date);
        $this->assertSame('1', (string) $appraisal->is_feedback_private);
        $this->assertSame('Mohon fokus pada konsistensi SOP outlet.', (string) $appraisal->invitation_note);
        $this->assertDatabaseCount('hr_notifications', 1);

        $this->actingAs($hrd)
            ->post(route('appraisals.remind', $appraisal->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('hr_notifications', 2);
        $this->assertDatabaseHas('hr_notifications', [
            'user_id' => $appraiser->id,
            'type' => 'appraisal_reminder',
        ]);
    }

    private function ensureSupportTables(): void
    {
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

        if (!Schema::hasTable('appraisal_periods')) {
            Schema::create('appraisal_periods', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('type')->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisals')) {
            Schema::create('appraisals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('appraisal_period_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('appraiser_id');
                $table->date('date_appraised')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('last_reminded_at')->nullable();
                $table->boolean('is_feedback_private')->default(true);
                $table->text('invitation_note')->nullable();
                $table->decimal('final_score', 8, 2)->nullable();
                $table->string('final_result')->nullable();
                $table->text('notes_hrd')->nullable();
                $table->string('generated_letter_number')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hr_notifications')) {
            Schema::create('hr_notifications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type');
                $table->string('title');
                $table->text('body')->nullable();
                $table->date('due_date')->nullable();
                $table->boolean('is_read')->default(false);
                $table->string('unique_key')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }
}




