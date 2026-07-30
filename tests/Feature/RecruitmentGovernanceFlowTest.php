<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\AssessmentForm;
use App\Models\Candidate;
use App\Models\CandidateActivityLog;
use App\Models\RecruitmentSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecruitmentGovernanceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSupportTables();
    }

    public function test_reject_and_restore_write_audit_trail_and_respect_retention_window(): void
    {
        RecruitmentSetting::setValue('retention_rejected_days', '14');

        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidate = Candidate::query()->create([
            'full_name' => 'Kandidat Retensi',
            'email' => 'retensi@example.com',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.index'))
            ->post(route('candidates.reject', $candidate))
            ->assertRedirect(route('candidates.index'))
            ->assertSessionHas('success');

        $candidate->refresh();
        $this->assertSame(Candidate::STATUS_REJECTED, $candidate->status);
        $this->assertNotNull($candidate->rejected_at);
        $this->assertDatabaseHas('candidate_activity_logs', [
            'candidate_id' => $candidate->id,
            'actor_user_id' => $hrd->id,
            'action_type' => 'candidate_rejected',
            'old_status' => Candidate::STATUS_APPLIED,
            'new_status' => Candidate::STATUS_REJECTED,
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.index', ['tab' => 'rejected']))
            ->post(route('candidates.restore', $candidate))
            ->assertSessionHas('success');

        $candidate->refresh();
        $this->assertSame(Candidate::STATUS_APPLIED, $candidate->status);
        $this->assertNull($candidate->rejected_at);
        $this->assertDatabaseHas('candidate_activity_logs', [
            'candidate_id' => $candidate->id,
            'actor_user_id' => $hrd->id,
            'action_type' => 'candidate_restored',
            'old_status' => Candidate::STATUS_REJECTED,
            'new_status' => Candidate::STATUS_APPLIED,
        ]);

        Candidate::withoutEvents(function () use ($candidate): void {
            $candidate->forceFill([
                'status' => Candidate::STATUS_REJECTED,
                'rejected_at' => now()->subDays(20),
                'accepted_at' => null,
                'blocked_at' => null,
            ])->save();
        });

        $this->actingAs($hrd)
            ->from(route('candidates.index', ['tab' => 'rejected']))
            ->post(route('candidates.restore', $candidate))
            ->assertSessionHas('error');

        $candidate->refresh();
        $this->assertSame(Candidate::STATUS_REJECTED, $candidate->status);
    }

    public function test_bulk_reject_updates_candidates_and_writes_audit_logs(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidateA = Candidate::query()->create(['full_name' => 'Bulk Satu', 'email' => 'bulk1@example.com', 'status' => Candidate::STATUS_APPLIED]);
        $candidateB = Candidate::query()->create(['full_name' => 'Bulk Dua', 'email' => 'bulk2@example.com', 'status' => Candidate::STATUS_APPLIED]);
        $candidateIds = [$candidateA->id, $candidateB->id];

        $this->actingAs($hrd)
            ->from(route('candidates.index'))
            ->post(route('candidates.bulk-status'), [
                'candidate_ids' => $candidateIds,
                'action' => 'reject',
            ])
            ->assertSessionHas('success');

        $candidateA->refresh();
        $candidateB->refresh();
        $this->assertSame(Candidate::STATUS_REJECTED, $candidateA->status);
        $this->assertSame(Candidate::STATUS_REJECTED, $candidateB->status);

        $this->assertSame(
            2,
            CandidateActivityLog::query()
                ->where('actor_user_id', $hrd->id)
                ->where('action_type', 'candidate_rejected')
                ->whereIn('candidate_id', $candidateIds)
                ->count()
        );
    }

    public function test_candidate_index_done_tab_renders_candidate_list_without_500(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $user = User::factory()->create(['email' => 'done-tab@example.com']);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Kandidat Done Tab',
                'photo_path' => 'profiles/done-tab.jpg',
                'email' => 'done-tab@example.com',
            ],
        ]);

        Candidate::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Kandidat Done Tab',
            'email' => 'done-tab@example.com',
            'status' => Candidate::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        $this->actingAs($hrd)
            ->get(route('candidates.index', ['tab' => 'done']))
            ->assertOk()
            ->assertSee('Kandidat Done Tab');
    }

    public function test_candidate_update_can_switch_to_shortlisted_without_status_truncation(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidate = Candidate::query()->create([
            'full_name' => 'Kandidat Shortlist',
            'email' => 'shortlist@example.com',
            'status' => Candidate::STATUS_APPLIED,
            'accepted_at' => now(),
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.edit', $candidate))
            ->put(route('candidates.update', $candidate), [
                'full_name' => 'Kandidat Shortlist',
                'email' => 'shortlist@example.com',
                'phone' => '081200000123',
                'nik' => '3175000000000100',
                'status' => Candidate::STATUS_SHORTLISTED,
                'notes' => 'Masuk shortlist administratif',
            ])
            ->assertRedirect(route('candidates.index'))
            ->assertSessionHas('success');

        $candidate->refresh();
        $this->assertSame(Candidate::STATUS_SHORTLISTED, $candidate->status);
        $this->assertNull($candidate->accepted_at);
        $this->assertNull($candidate->rejected_at);
        $this->assertNull($candidate->blocked_at);
    }

    public function test_candidate_update_can_switch_to_blocked_and_set_block_timestamp(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidate = Candidate::query()->create([
            'full_name' => 'Kandidat Blocked',
            'email' => 'blocked-update@example.com',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.edit', $candidate))
            ->put(route('candidates.update', $candidate), [
                'full_name' => 'Kandidat Blocked',
                'email' => 'blocked-update@example.com',
                'phone' => '081200000124',
                'nik' => '3175000000000101',
                'status' => Candidate::STATUS_BLOCKED,
                'notes' => 'Diblok karena kebijakan HRD',
            ])
            ->assertRedirect(route('candidates.index'))
            ->assertSessionHas('success');

        $candidate->refresh();
        $this->assertSame(Candidate::STATUS_BLOCKED, $candidate->status);
        $this->assertNotNull($candidate->blocked_at);
        $this->assertNull($candidate->accepted_at);
        $this->assertNull($candidate->rejected_at);
    }

    public function test_candidate_detail_page_renders_scoring_and_restore_metadata_without_500(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $user = User::factory()->create(['email' => 'detail@example.com']);
        $candidate = Candidate::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Kandidat Detail',
            'email' => 'detail@example.com',
            'status' => Candidate::STATUS_BLOCKED,
            'blocked_at' => now()->subDay(),
        ]);

        ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => 'Kandidat Detail',
                'photo_path' => 'profiles/detail-photo.jpg',
            ],
        ]);

        $this->actingAs($hrd)
            ->get(route('candidates.show', $candidate))
            ->assertOk()
            ->assertSee('Kandidat Detail')
            ->assertSee('Manual Input Penilaian')
            ->assertSee('Restore tersedia sampai');
    }

    public function test_bulk_activate_tests_opens_assignments_for_new_candidates(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidateA = Candidate::query()->create(['full_name' => 'Tes A', 'email' => 'tes-a@example.com', 'status' => Candidate::STATUS_APPLIED]);
        $candidateB = Candidate::query()->create(['full_name' => 'Tes B', 'email' => 'tes-b@example.com', 'status' => Candidate::STATUS_APPLIED]);
        $iqForm = AssessmentForm::query()->create([
            'code' => 'IQ-BULK',
            'name' => 'IQ Bulk',
            'type' => AssessmentForm::TYPE_IQ,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $discForm = AssessmentForm::query()->create([
            'code' => 'DISC-BULK',
            'name' => 'DISC Bulk',
            'type' => AssessmentForm::TYPE_DISC,
            'duration_minutes' => 25,
            'is_active' => true,
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.index', ['tab' => 'new']))
            ->post(route('candidates.bulk-activate-tests'), [
                'candidate_ids' => [$candidateA->id, $candidateB->id],
                'action' => 'both',
                'iq_form_id' => $iqForm->id,
                'disc_form_id' => $discForm->id,
            ])
            ->assertRedirect(route('candidates.index', ['tab' => 'new']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('form_assignments', [
            'candidate_id' => $candidateA->id,
            'form_id' => $iqForm->id,
            'status' => 'opened',
        ]);
        $this->assertDatabaseHas('form_assignments', [
            'candidate_id' => $candidateA->id,
            'form_id' => $discForm->id,
            'status' => 'opened',
        ]);
        $this->assertDatabaseHas('form_assignments', [
            'candidate_id' => $candidateB->id,
            'form_id' => $iqForm->id,
            'status' => 'opened',
        ]);
        $this->assertDatabaseHas('form_assignments', [
            'candidate_id' => $candidateB->id,
            'form_id' => $discForm->id,
            'status' => 'opened',
        ]);
    }

    public function test_open_test_and_manual_assessment_write_audit_trail(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $candidate = Candidate::query()->create([
            'full_name' => 'Kandidat Audit',
            'email' => 'audit@example.com',
            'status' => Candidate::STATUS_APPLIED,
        ]);
        $form = AssessmentForm::query()->create([
            'code' => 'IQ-01',
            'name' => 'IQ Dasar',
            'type' => AssessmentForm::TYPE_IQ,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.show', $candidate))
            ->post(route('candidates.tests.open', $candidate), ['form_id' => $form->id])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('candidate_activity_logs', [
            'candidate_id' => $candidate->id,
            'actor_user_id' => $hrd->id,
            'action_type' => 'test_opened',
            'new_status' => 'opened',
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.show', $candidate))
            ->post(route('candidates.assessment.update', $candidate), [
                'iq_score' => 120,
                'interview_score' => 85,
                'disc_d' => 10,
                'disc_i' => 11,
                'disc_s' => 12,
                'disc_c' => 13,
                'interview_notes' => 'Layak lanjut.',
                'status' => 'passed',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('candidate_activity_logs', [
            'candidate_id' => $candidate->id,
            'actor_user_id' => $hrd->id,
            'action_type' => 'assessment_created',
            'new_status' => 'passed',
        ]);
    }

    public function test_accept_links_existing_user_and_promotes_candidate_without_500_error(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $existingUser = User::factory()->create([
            'email' => 'promosi@example.com',
            'role' => User::ROLE_CANDIDATE,
            'employee_id' => null,
        ]);

        $candidate = Candidate::query()->create([
            'full_name' => 'Kandidat Promosi',
            'email' => 'promosi@example.com',
            'status' => Candidate::STATUS_APPLIED,
        ]);

        $this->actingAs($hrd)
            ->from(route('candidates.index'))
            ->post(route('candidates.accept', $candidate))
            ->assertRedirect(route('candidates.index'))
            ->assertSessionHas('success');

        $candidate->refresh();
        $existingUser->refresh();

        $this->assertSame(Candidate::STATUS_ACCEPTED, $candidate->status);
        $this->assertSame($existingUser->id, $candidate->user_id);
        $this->assertNotNull($candidate->accepted_at);
        $this->assertNotNull($existingUser->employee_id);
        $this->assertSame(User::ROLE_PROBATION, $existingUser->role);
        $this->assertDatabaseHas('employees', [
            'id' => $existingUser->employee_id,
            'full_name' => 'Kandidat Promosi',
            'email_private' => 'promosi@example.com',
            'status_employment' => 'probation',
        ]);
    }

    private function ensureSupportTables(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table): void {
                $table->id();
                $table->string('nik')->nullable();
                $table->string('external_id')->nullable();
                $table->string('full_name')->nullable();
                $table->string('email_private')->nullable();
                $table->string('phone_number')->nullable();
                $table->date('join_date')->nullable();
                $table->date('probation_end_date')->nullable();
                $table->string('status_employment')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('applicant_profiles')) {
            Schema::create('applicant_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
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



