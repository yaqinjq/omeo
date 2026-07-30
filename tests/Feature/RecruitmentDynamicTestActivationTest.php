<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\Candidate;
use App\Models\CandidateActivityLog;
use App\Models\Department;
use App\Models\FormAssignment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecruitmentDynamicTestActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_activate_tests_supports_dynamic_form_selection_and_listing(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidateA = Candidate::query()->create(['full_name' => 'Finance A', 'email' => 'finance-a@example.com', 'status' => Candidate::STATUS_APPLIED]);
        $candidateB = Candidate::query()->create(['full_name' => 'Ops B', 'email' => 'ops-b@example.com', 'status' => Candidate::STATUS_APPLIED]);

        $fatForm = AssessmentForm::query()->create([
            'code' => 'FAT-001',
            'name' => 'FAT Finance Accounting',
            'type' => AssessmentForm::TYPE_CUSTOM,
            'duration_minutes' => 35,
            'is_active' => true,
        ]);
        $tiuForm = AssessmentForm::query()->create([
            'code' => 'TIU-001',
            'name' => 'TIU Operational',
            'type' => AssessmentForm::TYPE_CUSTOM,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $customForm = AssessmentForm::query()->create([
            'code' => 'CUS-001',
            'name' => 'Tes Observasi Custom',
            'type' => AssessmentForm::TYPE_CUSTOM,
            'duration_minutes' => 20,
            'is_active' => true,
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.index', ['tab' => 'new']))
            ->post(route('candidates.bulk-activate-tests'), [
                'candidate_ids' => [$candidateA->id, $candidateB->id],
                'form_ids' => [$fatForm->id, $tiuForm->id],
            ])
            ->assertRedirect(route('candidates.index', ['tab' => 'new']))
            ->assertSessionHas('success');

        foreach ([$candidateA, $candidateB] as $candidate) {
            $this->assertDatabaseHas('form_assignments', [
                'candidate_id' => $candidate->id,
                'form_id' => $fatForm->id,
                'status' => FormAssignment::STATUS_OPENED,
            ]);
            $this->assertDatabaseHas('form_assignments', [
                'candidate_id' => $candidate->id,
                'form_id' => $tiuForm->id,
                'status' => FormAssignment::STATUS_OPENED,
            ]);
            $this->assertDatabaseMissing('form_assignments', [
                'candidate_id' => $candidate->id,
                'form_id' => $customForm->id,
            ]);
        }

        $this->actingAs($hrd)
            ->get(route('candidates.index', ['tab' => 'active']))
            ->assertOk()
            ->assertSeeText('FAT Finance Accounting')
            ->assertSeeText('TIU Operational')
            ->assertSeeText('Opened');
    }

    public function test_open_test_supports_multiple_dynamic_forms_for_single_candidate(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidate = Candidate::query()->create([
            'full_name' => 'Candidate Multi Test',
            'email' => 'multi@example.com',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $discForm = AssessmentForm::query()->create([
            'code' => 'DISC-MULTI',
            'name' => 'DISC Multi',
            'type' => AssessmentForm::TYPE_DISC,
            'duration_minutes' => 25,
            'is_active' => true,
        ]);
        $fatForm = AssessmentForm::query()->create([
            'code' => 'FAT-MULTI',
            'name' => 'FAT Multi',
            'type' => AssessmentForm::TYPE_CUSTOM,
            'duration_minutes' => 35,
            'is_active' => true,
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.index', ['tab' => 'new']))
            ->post(route('candidates.tests.open', $candidate), [
                'form_ids' => [$discForm->id, $fatForm->id],
            ])
            ->assertRedirect(route('candidates.index', ['tab' => 'new']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('form_assignments', [
            'candidate_id' => $candidate->id,
            'form_id' => $discForm->id,
            'status' => FormAssignment::STATUS_OPENED,
        ]);
        $this->assertDatabaseHas('form_assignments', [
            'candidate_id' => $candidate->id,
            'form_id' => $fatForm->id,
            'status' => FormAssignment::STATUS_OPENED,
        ]);

        $this->assertSame(
            2,
            CandidateActivityLog::query()
                ->where('candidate_id', $candidate->id)
                ->where('action_type', 'test_opened')
                ->where('new_status', FormAssignment::STATUS_OPENED)
                ->count()
        );
    }

    public function test_recruitment_index_groups_forms_by_department_audience(): void
    {
        $this->ensureDepartmentsTable();

        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        Candidate::query()->create(['full_name' => 'Candidate Grouping', 'email' => 'grouping@example.com', 'status' => Candidate::STATUS_APPLIED]);

        $finance = Department::query()->create(['code' => 'FIN', 'name' => 'Finance']);
        $operational = Department::query()->create(['code' => 'OPS', 'name' => 'Operational']);

        AssessmentForm::query()->create([
            'code' => 'FAT-DEP',
            'name' => 'FAT Finance Accounting',
            'type' => AssessmentForm::TYPE_CUSTOM,
            'department_id' => $finance->id,
            'duration_minutes' => 35,
            'is_active' => true,
        ]);
        AssessmentForm::query()->create([
            'code' => 'TIU-DEP',
            'name' => 'TIU Operational',
            'type' => AssessmentForm::TYPE_CUSTOM,
            'department_id' => $operational->id,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        AssessmentForm::query()->create([
            'code' => 'IQ-GEN',
            'name' => 'IQ Umum',
            'type' => AssessmentForm::TYPE_IQ,
            'duration_minutes' => 25,
            'is_active' => true,
        ]);

        $this->actingAs($hrd)
            ->get(route('candidates.index', ['tab' => 'new']))
            ->assertOk()
            ->assertSeeText('Finance')
            ->assertSeeText('Operational')
            ->assertSeeText('Umum / Semua Departemen')
            ->assertSeeText('FAT Finance Accounting')
            ->assertSeeText('TIU Operational')
            ->assertSeeText('IQ Umum');
    }

    public function test_candidate_detail_page_displays_application_summary(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidate = Candidate::query()->create([
            'full_name' => 'Candidate Detail',
            'email' => 'candidate-detail@example.com',
            'status' => Candidate::STATUS_SHORTLISTED,
            'applied_position_name' => 'Staff Finance',
            'applied_department_name' => 'Finance',
            'applied_outlet_name' => 'Outlet Jakarta',
        ]);

        $this->actingAs($hrd)
            ->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertSeeText('Lamaran Kandidat')
            ->assertSeeText('Staff Finance')
            ->assertSeeText('Finance')
            ->assertSeeText('Outlet Jakarta');
    }
    private function ensureDepartmentsTable(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->nullable();
                $table->string('name');
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }
}
