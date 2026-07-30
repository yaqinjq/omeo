<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\Candidate;
use App\Models\FormAssignment;
use App\Models\FormAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentTestedListTest extends TestCase
{
    use RefreshDatabase;

    public function test_tested_tab_defaults_to_latest_activity_and_supports_type_filtering(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);

        $iqForm = AssessmentForm::query()->create([
            'code' => 'IQ-SORT',
            'name' => 'IQ Umum',
            'type' => AssessmentForm::TYPE_IQ,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $discForm = AssessmentForm::query()->create([
            'code' => 'DISC-SORT',
            'name' => 'DISC Umum',
            'type' => AssessmentForm::TYPE_DISC,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $tiuForm = AssessmentForm::query()->create([
            'code' => 'TIU-SORT',
            'name' => 'TIU Baseline',
            'type' => AssessmentForm::TYPE_CUSTOM,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $older = Candidate::query()->create(['full_name' => 'Alpha Older', 'email' => 'alpha@example.com', 'status' => Candidate::STATUS_APPLIED]);
        $middle = Candidate::query()->create(['full_name' => 'Bravo Middle', 'email' => 'bravo@example.com', 'status' => Candidate::STATUS_APPLIED]);
        $latest = Candidate::query()->create(['full_name' => 'Charlie Latest', 'email' => 'charlie@example.com', 'status' => Candidate::STATUS_APPLIED]);
        $active = Candidate::query()->create(['full_name' => 'Delta Active', 'email' => 'delta@example.com', 'status' => Candidate::STATUS_APPLIED]);

        $this->createSubmittedAssignment($older, $iqForm, now()->subDays(2), $hrd->id);
        $this->createSubmittedAssignment($middle, $discForm, now()->subDay(), $hrd->id);
        $this->createSubmittedAssignment($latest, $tiuForm, now(), $hrd->id);

        FormAssignment::query()->create([
            'form_id' => $iqForm->id,
            'candidate_id' => $active->id,
            'status' => FormAssignment::STATUS_OPENED,
            'opened_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'created_by' => $hrd->id,
        ]);

        $response = $this->actingAs($hrd)
            ->get(route('candidates.index', ['tab' => 'tested', 'sort' => 'latest_test']));

        $response->assertOk();
        $response->assertSeeTextInOrder(['Charlie Latest', 'Bravo Middle', 'Alpha Older']);
        $response->assertDontSeeText('Delta Active');

        $response = $this->actingAs($hrd)
            ->get(route('candidates.index', ['tab' => 'tested', 'test_type' => 'tiu']));

        $response->assertOk();
        $response->assertSeeText('Charlie Latest');
        $response->assertDontSeeText('Bravo Middle');
        $response->assertDontSeeText('Alpha Older');
    }

    private function createSubmittedAssignment(Candidate $candidate, AssessmentForm $form, $submittedAt, int $userId): void
    {
        $assignment = FormAssignment::query()->create([
            'form_id' => $form->id,
            'candidate_id' => $candidate->id,
            'status' => FormAssignment::STATUS_SUBMITTED,
            'opened_at' => $submittedAt->copy()->subMinutes(30),
            'expires_at' => $submittedAt->copy()->addMinutes(30),
            'closed_at' => $submittedAt,
            'created_by' => $userId,
        ]);

        FormAttempt::query()->create([
            'form_assignment_id' => $assignment->id,
            'started_at' => $submittedAt->copy()->subMinutes(25),
            'submitted_at' => $submittedAt,
            'time_spent_seconds' => 1200,
            'computed_result' => [],
        ]);
    }
}
