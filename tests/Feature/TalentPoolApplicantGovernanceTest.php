<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\RecruitmentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TalentPoolApplicantGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_reject_incomplete_command_rejects_only_expired_active_profiles(): void
    {
        RecruitmentSetting::setValue('applicant_incomplete_auto_reject_days', '14');

        $expired = $this->createApplicantProfile('Expired Applicant', 'expired@example.com', completed: false, createdDaysAgo: 20);
        $fresh = $this->createApplicantProfile('Fresh Applicant', 'fresh@example.com', completed: false, createdDaysAgo: 5);
        $withCandidate = $this->createApplicantProfile('With Candidate', 'linked@example.com', completed: false, createdDaysAgo: 30);
        Candidate::query()->create([
            'user_id' => $withCandidate->user_id,
            'full_name' => 'With Candidate',
            'email' => 'linked@example.com',
            'status' => Candidate::STATUS_SHORTLISTED,
            'applied_at' => now(),
        ]);

        $this->artisan('applicants:auto-reject-incomplete')
            ->assertExitCode(0);

        $expired->refresh();
        $fresh->refresh();
        $withCandidate->refresh();

        $this->assertSame(ApplicantProfile::GOVERNANCE_STATUS_REJECTED, $expired->governance_status);
        $this->assertSame('auto_reject_incomplete_timeout', $expired->governance_reason);
        $this->assertNotNull($expired->rejected_at);
        $this->assertDatabaseHas('applicant_profile_activity_logs', [
            'applicant_profile_id' => $expired->id,
            'action_type' => 'applicant_rejected',
            'new_status' => ApplicantProfile::GOVERNANCE_STATUS_REJECTED,
        ]);

        $this->assertSame(ApplicantProfile::GOVERNANCE_STATUS_ACTIVE, $fresh->governance_status);
        $this->assertSame(ApplicantProfile::GOVERNANCE_STATUS_ACTIVE, $withCandidate->governance_status);
    }

    public function test_bulk_reject_filtered_scope_processes_all_matching_results(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $profileA = $this->createApplicantProfile('Bulk Reject Alpha', 'bulk-alpha@example.com', completed: false);
        $profileB = $this->createApplicantProfile('Bulk Reject Beta', 'bulk-beta@example.com', completed: false);
        $other = $this->createApplicantProfile('Outside Filter', 'outside@example.com', completed: false);

        $this->actingAs($hrd)
            ->from(route('hrd.applicants.index', ['tab' => 'incomplete']))
            ->post(route('hrd.applicants.bulk-action'), [
                'action' => 'reject',
                'selection_scope' => 'filtered',
                'tab' => 'incomplete',
                'search' => 'Bulk Reject',
                'sort' => 'updated_at',
                'order' => 'desc',
            ])
            ->assertSessionHas('success');

        $profileA->refresh();
        $profileB->refresh();
        $other->refresh();

        $this->assertSame(ApplicantProfile::GOVERNANCE_STATUS_REJECTED, $profileA->governance_status);
        $this->assertSame(ApplicantProfile::GOVERNANCE_STATUS_REJECTED, $profileB->governance_status);
        $this->assertSame(ApplicantProfile::GOVERNANCE_STATUS_ACTIVE, $other->governance_status);

        $this->assertSame(2, ApplicantProfile::query()->where('governance_status', ApplicantProfile::GOVERNANCE_STATUS_REJECTED)->count());
        $this->assertSame(
            2,
            \App\Models\ApplicantProfileActivityLog::query()
                ->where('actor_user_id', $hrd->id)
                ->where('action_type', 'applicant_rejected')
                ->count()
        );
    }

    public function test_bulk_blacklist_and_archive_update_governance_safely(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $blacklistProfile = $this->createApplicantProfile('Blacklist Me', 'blacklist@example.com', completed: false, nik: '3175000000001101');
        $archiveProfile = $this->createApplicantProfile('Archive Me', 'archive@example.com', completed: false, nik: '3175000000001102');

        $this->actingAs($hrd)
            ->post(route('hrd.applicants.bulk-action'), [
                'action' => 'blacklist',
                'selection_scope' => 'page',
                'profile_ids' => [$blacklistProfile->id],
                'tab' => 'incomplete',
                'search' => '',
                'sort' => 'updated_at',
                'order' => 'desc',
            ])
            ->assertSessionHas('success');

        $this->actingAs($hrd)
            ->post(route('hrd.applicants.bulk-action'), [
                'action' => 'archive',
                'selection_scope' => 'page',
                'profile_ids' => [$archiveProfile->id],
                'tab' => 'incomplete',
                'search' => '',
                'sort' => 'updated_at',
                'order' => 'desc',
            ])
            ->assertSessionHas('success');

        $blacklistProfile->refresh();
        $archiveTrashed = ApplicantProfile::withTrashed()->findOrFail($archiveProfile->id);

        $this->assertSame(ApplicantProfile::GOVERNANCE_STATUS_BLACKLISTED, $blacklistProfile->governance_status);
        if (Schema::hasColumn('candidate_blacklists', 'identifier_type')) {
            $this->assertDatabaseHas('candidate_blacklists', [
                'identifier_type' => 'nik',
                'identifier_value' => '3175000000001101',
            ]);
        } else {
            $this->assertDatabaseHas('candidate_blacklists', [
                'nik' => '3175000000001101',
            ]);
        }

        $this->assertSame(ApplicantProfile::GOVERNANCE_STATUS_ARCHIVED, $archiveTrashed->governance_status);
        $this->assertNotNull($archiveTrashed->deleted_at);

        $this->actingAs($hrd)
            ->get(route('hrd.applicants.index', ['tab' => 'incomplete']))
            ->assertOk()
            ->assertDontSee('Blacklist Me')
            ->assertDontSee('Archive Me');
    }

    public function test_bulk_pass_creates_shortlisted_candidates_and_moves_profile_to_verified_tab(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $profileA = $this->createApplicantProfile('Pass Alpha', 'pass-alpha@example.com', completed: true, nik: '3175000000002201');
        $profileB = $this->createApplicantProfile('Pass Beta', 'pass-beta@example.com', completed: true, nik: '3175000000002202');

        $this->actingAs($hrd)
            ->post(route('hrd.applicants.bulk-action'), [
                'action' => 'pass',
                'selection_scope' => 'page',
                'profile_ids' => [$profileA->id, $profileB->id],
                'tab' => 'unverified',
                'search' => '',
                'sort' => 'updated_at',
                'order' => 'desc',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('candidates', [
            'user_id' => $profileA->user_id,
            'status' => Candidate::STATUS_SHORTLISTED,
        ]);
        $this->assertDatabaseHas('candidates', [
            'user_id' => $profileB->user_id,
            'status' => Candidate::STATUS_SHORTLISTED,
        ]);

        $this->actingAs($hrd)
            ->get(route('candidates.index', ['tab' => 'new']))
            ->assertOk()
            ->assertSee('Pass Alpha')
            ->assertSee('Pass Beta');

        $this->actingAs($hrd)
            ->get(route('hrd.applicants.index', ['tab' => 'verified']))
            ->assertOk()
            ->assertSee('Pass Alpha')
            ->assertSee('Pass Beta');

        $this->actingAs($hrd)
            ->get(route('hrd.applicants.index', ['tab' => 'unverified']))
            ->assertOk()
            ->assertDontSee('Pass Alpha')
            ->assertDontSee('Pass Beta');
    }

    public function test_talent_pool_pages_display_application_position_summary(): void
    {
        $hrd = User::factory()->create(['role' => User::ROLE_HRD]);
        $profile = $this->createApplicantProfile('Applicant Summary', 'summary@applicant.test', completed: true, nik: '3175000000003301');

        $personal = $profile->personal_json ?? [];
        $personal['applied_position_name'] = 'Crew Outlet';
        $personal['applied_department_name'] = 'Operations';
        $personal['applied_outlet_name'] = 'Outlet Bandung';
        $profile->personal_json = $personal;
        $profile->save();

        $this->actingAs($hrd)
            ->get(route('hrd.applicants.index', ['tab' => 'unverified']))
            ->assertOk()
            ->assertSeeText('Crew Outlet')
            ->assertSeeText('Operations')
            ->assertSeeText('Outlet Bandung');

        $this->actingAs($hrd)
            ->get(route('hrd.applicants.show', $profile))
            ->assertOk()
            ->assertSeeText('Lamaran Kandidat')
            ->assertSeeText('Crew Outlet')
            ->assertSeeText('Operations')
            ->assertSeeText('Outlet Bandung');
    }
    private function createApplicantProfile(string $name, string $email, bool $completed, int $createdDaysAgo = 1, ?string $nik = null): ApplicantProfile
    {
        $user = User::factory()->create([
            'role' => User::ROLE_APPLICANT,
            'email' => $email,
        ]);

        $profile = ApplicantProfile::query()->create([
            'user_id' => $user->id,
            'personal_json' => [
                'full_name' => $name,
                'email' => $email,
                'ktp_number' => $nik ?? ('317500000000' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT)),
                'place_of_birth' => 'Bandung',
                'date_of_birth' => '1998-01-10',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'marital_status' => 'Belum Menikah',
                'whatsapp' => '081234567890',
                'salary_expectation' => '5000000',
                'photo_path' => 'applicants/photos/example.jpg',
                'ktp_path' => 'applicants/ktp/example.jpg',
                'cv_path' => 'applicants/cv/example.pdf',
                'reference_contacts' => [[
                    'name' => 'Supervisor',
                    'relation' => 'Atasan',
                    'company' => 'PT Test',
                    'phone' => '081111111111',
                ]],
            ],
            'address_json' => [
                'ktp_address' => 'Jl. Mawar',
                'domicile_address' => 'Jl. Melati',
            ],
            'family_json' => [[
                'relation' => 'Ayah',
                'name' => 'Bapak',
                'gender' => 'Laki-laki',
                'dob' => '1970-01-01',
                'education' => 'SMA',
                'job' => 'Wiraswasta',
            ]],
            'education_json' => [[
                'level' => 'S1',
                'school' => 'Universitas Test',
                'major' => 'Manajemen',
                'year_in' => '2016',
                'year_out' => '2020',
            ]],
            'language_json' => [[
                'language' => 'Indonesia',
                'speaking' => 'Baik',
                'writing' => 'Baik',
            ]],
            'work_json' => [[
                'company' => 'PT Test',
                'position' => 'Crew',
                'date_start' => '2021-01-01',
                'salary' => '3500000',
                'reason' => 'Kontrak selesai',
            ]],
            'medical_json' => [[
                'illness' => 'Tidak ada',
                'year' => '2023',
                'hospitalized' => 'Tidak',
            ]],
            'social_json' => [[
                'platform' => 'Instagram',
                'handle' => '@'.strtolower(str_replace(' ', '', $name)),
            ]],
            'completed_at' => $completed ? now() : null,
        ]);

        ApplicantProfile::withoutEvents(function () use ($profile, $createdDaysAgo): void {
            $profile->forceFill([
                'created_at' => now()->subDays($createdDaysAgo),
                'updated_at' => now()->subDays(max(0, $createdDaysAgo - 1)),
            ])->save();
        });

        return $profile->fresh();
    }
}
