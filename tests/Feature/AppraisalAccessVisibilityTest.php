<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\AppraisalProbationReminderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppraisalAccessVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_employee_and_evaluator_only_see_authorized_appraisals(): void
    {
        $employeeA = Employee::query()->create([
            'nik' => '3175000000000101',
            'full_name' => 'Probation A',
            'status_employment' => 'probation',
        ]);
        $employeeB = Employee::query()->create([
            'nik' => '3175000000000102',
            'full_name' => 'Probation B',
            'status_employment' => 'probation',
        ]);

        $employeeUserA = User::factory()->create(['role' => User::ROLE_PROBATION, 'employee_id' => $employeeA->id]);
        $employeeUserB = User::factory()->create(['role' => User::ROLE_PROBATION, 'employee_id' => $employeeB->id]);
        $evaluatorA = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $evaluatorB = User::factory()->create(['role' => User::ROLE_MANAGER]);

        $periodId = DB::table('appraisal_periods')->insertGetId([
            'name' => 'Probation April',
            'type' => 'probation',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appraisalA = DB::table('appraisals')->insertGetId([
            'appraisal_period_id' => $periodId,
            'employee_id' => $employeeA->id,
            'appraiser_id' => $evaluatorA->id,
            'date_appraised' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'invited_at' => now(),
            'submitted_at' => now(),
            'is_feedback_private' => true,
            'feedback_strengths' => 'Kuat dalam koordinasi tim outlet.',
            'feedback_improvements' => 'Perlu lebih konsisten membuka shift pagi.',
            'feedback_notes' => 'Tetap jaga komunikasi dengan leader outlet.',
            'final_score' => 84.5,
            'final_result' => 'Lulus',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appraisalB = DB::table('appraisals')->insertGetId([
            'appraisal_period_id' => $periodId,
            'employee_id' => $employeeB->id,
            'appraiser_id' => $evaluatorB->id,
            'date_appraised' => now()->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'invited_at' => now(),
            'submitted_at' => now(),
            'is_feedback_private' => true,
            'feedback_strengths' => 'Feedback B',
            'feedback_improvements' => 'Improvement B',
            'feedback_notes' => 'Notes B',
            'final_score' => 70.0,
            'final_result' => 'Perpanjang Probation',
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('appraisal_component_scores')->insert([
            'appraisal_id' => $appraisalA,
            'component_key' => 'training_participation',
            'component_label' => 'Training Participation',
            'source_type' => 'system_training',
            'score_normalized' => 88,
            'weight' => 15,
            'notes' => '2/2 training selesai.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('appraisal_indicators')->insert([
            'id' => 1,
            'category' => 'Sikap Kerja',
            'question' => 'Disiplin terhadap SOP',
            'weight' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('appraisal_details')->insert([
            'appraisal_id' => $appraisalA,
            'appraisal_indicator_id' => 1,
            'score' => 4,
            'comment' => 'Sudah cukup baik di lapangan.',
        ]);

        $this->actingAs($employeeUserA)
            ->get(route('appraisals.my'))
            ->assertOk()
            ->assertSeeText('Probation April')
            ->assertSeeText('Kuat dalam koordinasi tim outlet.')
            ->assertDontSeeText($evaluatorA->name)
            ->assertDontSeeText('Probation B');

        $this->actingAs($employeeUserA)
            ->get(route('appraisals.show', $appraisalA))
            ->assertOk()
            ->assertSeeText('Perlu lebih konsisten membuka shift pagi.')
            ->assertDontSeeText($evaluatorA->name);

        $this->actingAs($employeeUserA)
            ->get(route('appraisals.show', $appraisalB))
            ->assertForbidden();

        $this->actingAs($evaluatorA)
            ->get(route('appraisals.evaluator'))
            ->assertOk()
            ->assertSeeText('Probation A')
            ->assertDontSeeText('Probation B');

        $this->actingAs($evaluatorA)
            ->get(route('appraisals.show', $appraisalA))
            ->assertOk()
            ->assertSeeText('Form Pengisian Evaluasi');

        $this->actingAs($evaluatorA)
            ->get(route('appraisals.show', $appraisalB))
            ->assertForbidden();

        $this->actingAs($employeeUserB)
            ->get(route('appraisals.show', $appraisalA))
            ->assertForbidden();
    }

    public function test_hrd_can_send_invitation_and_probation_reminder_is_generated(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $evaluator = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $employee = Employee::query()->create([
            'nik' => '3175000000000103',
            'full_name' => 'Probation Reminder',
            'status_employment' => 'probation',
            'probation_end_date' => now()->addDays(7)->toDateString(),
        ]);

        $periodId = DB::table('appraisal_periods')->insertGetId([
            'name' => 'Probation Mei',
            'type' => 'probation',
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrd)
            ->post(route('appraisals.assignment.generate'), [
                'appraisal_period_id' => $periodId,
                'employee_ids' => [$employee->id],
                'appraiser_user_ids' => [$evaluator->id],
                'date_appraised' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'invitation_note' => 'Fokus pada disiplin jadwal dan kesiapan role outlet.',
                'is_feedback_private' => '1',
            ])
            ->assertRedirect(route('appraisals.assignment'))
            ->assertSessionHas('success');

        $appraisalId = DB::table('appraisals')->value('id');
        $this->assertNotNull($appraisalId);
        $this->assertDatabaseHas('appraisal_invitation_logs', [
            'appraisal_id' => $appraisalId,
            'actor_user_id' => $hrd->id,
            'target_user_id' => $evaluator->id,
            'action' => 'invited',
        ]);

        $this->actingAs($hrd)
            ->post(route('appraisals.remind', $appraisalId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('appraisal_invitation_logs', [
            'appraisal_id' => $appraisalId,
            'actor_user_id' => $hrd->id,
            'target_user_id' => $evaluator->id,
            'action' => 'reminded',
        ]);

        $created = app(AppraisalProbationReminderService::class)->generate([7]);
        $this->assertGreaterThanOrEqual(1, $created);
        $this->assertDatabaseHas('hr_notifications', [
            'user_id' => $hrd->id,
            'type' => 'appraisal_probation_reminder',
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
                $table->unsignedBigInteger('invited_by_user_id')->nullable();
                $table->date('date_appraised')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('last_reminded_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->boolean('is_feedback_private')->default(true);
                $table->text('invitation_note')->nullable();
                $table->text('feedback_strengths')->nullable();
                $table->text('feedback_improvements')->nullable();
                $table->text('feedback_notes')->nullable();
                $table->decimal('final_score', 8, 2)->nullable();
                $table->string('final_result')->nullable();
                $table->text('notes_hrd')->nullable();
                $table->string('generated_letter_number')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisal_indicators')) {
            Schema::create('appraisal_indicators', function (Blueprint $table): void {
                $table->id();
                $table->string('category')->nullable();
                $table->string('question')->nullable();
                $table->unsignedInteger('weight')->default(0);
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

        if (!Schema::hasTable('appraisal_component_scores')) {
            Schema::create('appraisal_component_scores', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('appraisal_id');
                $table->string('component_key');
                $table->string('component_label');
                $table->string('source_type')->nullable();
                $table->decimal('score_raw', 8, 2)->nullable();
                $table->decimal('score_normalized', 8, 2)->nullable();
                $table->decimal('weight', 8, 2)->default(0);
                $table->text('notes')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('appraisal_invitation_logs')) {
            Schema::create('appraisal_invitation_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('appraisal_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->unsignedBigInteger('target_user_id')->nullable();
                $table->string('action');
                $table->text('notes')->nullable();
                $table->json('payload')->nullable();
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
