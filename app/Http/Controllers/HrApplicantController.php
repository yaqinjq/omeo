<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PromoteApplicantToCandidateAction;
use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Services\ApplicantGovernanceService;
use App\Services\ApplicantTalentPoolQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class HrApplicantController extends Controller
{
    public function __construct(
        private readonly ApplicantTalentPoolQuery $talentPoolQuery,
        private readonly ApplicantGovernanceService $governanceService,
        private readonly PromoteApplicantToCandidateAction $promoteApplicantToCandidateAction,
    ) {
    }

    public function index(Request $request)
    {
        $filters = $this->talentPoolQuery->filtersFromRequest($request);
        $signals = $this->talentPoolQuery->buildCandidateSignals();
        $governanceAvailable = $this->governanceService->isAvailable();

        $applicants = $this->talentPoolQuery
            ->build($filters)
            ->paginate($this->getPerPage(10))
            ->withQueryString();

        $applicants->getCollection()->transform(function (ApplicantProfile $profile) use ($signals) {
            $candidate = $this->talentPoolQuery->resolveCandidateForProfile($profile);
            $isVerified = $this->talentPoolQuery->isVerified($profile, $signals);

            $profile->setAttribute('linked_candidate', $candidate);
            $profile->setAttribute('is_verified', $isVerified);
            $profile->setAttribute('talent_pool_stage', $profile->talentPoolStage($isVerified));
            $profile->setAttribute('governance_status_label', $profile->governanceStatusLabel());
            $profile->setAttribute('profile_age_days', (int) $profile->created_at?->diffInDays(now()));
            $profile->setAttribute('can_govern', $this->governanceService->canGovern($profile));

            return $profile;
        });

        $counts = [
            ApplicantTalentPoolQuery::TAB_INCOMPLETE => $this->talentPoolQuery->build(array_merge($filters, ['tab' => ApplicantTalentPoolQuery::TAB_INCOMPLETE]), withUser: false)->count(),
            ApplicantTalentPoolQuery::TAB_UNVERIFIED => $this->talentPoolQuery->build(array_merge($filters, ['tab' => ApplicantTalentPoolQuery::TAB_UNVERIFIED]), withUser: false)->count(),
            ApplicantTalentPoolQuery::TAB_VERIFIED => $this->talentPoolQuery->build(array_merge($filters, ['tab' => ApplicantTalentPoolQuery::TAB_VERIFIED]), withUser: false)->count(),
        ];

        $bulkActions = $this->bulkActionsForTab($filters['tab'], $governanceAvailable);

        return view('hrd.applicants.index', [
            'applicants' => $applicants,
            'tab' => $filters['tab'],
            'counts' => $counts,
            'filters' => $filters,
            'bulkActions' => $bulkActions,
            'governanceAvailable' => $governanceAvailable,
        ]);
    }

    public function show(ApplicantProfile $profile)
    {
        $profile->loadMissing(['user', 'governedBy']);

        if (ApplicantProfile::supportsAuditLogTable()) {
            $profile->loadMissing('activityLogs.actor');
        } else {
            $profile->setRelation('activityLogs', collect());
        }

        $candidate = $this->talentPoolQuery->resolveCandidateForProfile($profile);
        $stats = [
            'iq' => null,
            'disc' => null,
            'interview_score' => null,
            'technical_score' => null,
            'culture_fit' => null,
            'disc_axis' => null,
        ];

        if ($candidate) {
            $candidate->loadMissing([
                'assessment',
                'formAssignments.form',
                'formAssignments.attempt',
                'user.employee',
            ]);

            $assessment = $candidate->assessment;
            $discAssignment = $candidate->formAssignments
                ->where('form.type', 'disc')
                ->sortByDesc('id')
                ->first();

            $discScores = $this->resolveDiscScores(
                is_array($assessment?->disc_result) ? $assessment->disc_result : [],
                is_array($discAssignment?->attempt?->computed_result) ? $discAssignment->attempt->computed_result : []
            );

            $dominantAxis = $this->resolveDominantDiscAxis($discScores);
            $hasDisc = array_sum($discScores) > 0;

            $stats = [
                'iq' => is_numeric($assessment?->iq_score) ? (int) $assessment->iq_score : null,
                'disc' => $hasDisc
                    ? ('D:' . $discScores['D'] . ' I:' . $discScores['I'] . ' S:' . $discScores['S'] . ' C:' . $discScores['C'])
                    : null,
                'interview_score' => is_numeric($assessment?->interview_score) ? (int) $assessment->interview_score : null,
                'technical_score' => null,
                'culture_fit' => null,
                'disc_axis' => $dominantAxis,
            ];
        }

        $signals = $this->talentPoolQuery->buildCandidateSignals();
        $isVerified = $this->talentPoolQuery->isVerified($profile, $signals);
        $stage = $profile->talentPoolStage($isVerified);
        $hideSalaryExpectation = $this->isOfficialProbationProfileCompleted($candidate);
        $canGovern = $this->governanceService->canGovern($profile);
        $governanceAvailable = $this->governanceService->isAvailable();

        return view('hrd.applicants.show_v4', compact(
            'profile',
            'stats',
            'isVerified',
            'candidate',
            'hideSalaryExpectation',
            'stage',
            'canGovern',
            'governanceAvailable'
        ));
    }

    public function exportPdf(ApplicantProfile $profile): RedirectResponse
    {
        $profile->loadMissing('user');

        $candidate = $this->talentPoolQuery->resolveCandidateForProfile($profile);
        if (! $candidate) {
            return back()->with('error', 'Export PDF belum bisa dilakukan. Kandidat recruitment belum ditemukan untuk profil ini.');
        }

        return redirect()->route('candidates.export-preview', $candidate);
    }

    public function markReviewed(ApplicantProfile $profile, Request $request): RedirectResponse
    {
        try {
            $candidate = $this->promoteApplicantToCandidateAction->execute($profile, $request->user(), [
                'source' => 'hrd.applicants.reviewed',
                'selection_scope' => 'single',
            ]);

            Log::info('HRD mark reviewed applicant', [
                'applicant_profile_id' => $profile->id,
                'candidate_id' => $candidate->id,
                'actor_user_id' => $request->user()?->id,
            ]);

            return back()->with('success', 'Pelamar berhasil diloloskan administrasi dan masuk ke Recruitment Kandidat.');
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $e) {
            Log::error('Gagal mark reviewed applicant', [
                'applicant_profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Terjadi kendala saat meloloskan administrasi. Silakan coba lagi atau hubungi tim IT.');
        }
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['pass', 'reject', 'blacklist', 'archive'])],
            'selection_scope' => ['required', Rule::in(['page', 'filtered'])],
            'profile_ids' => ['nullable', 'array'],
            'profile_ids.*' => ['integer'],
            'tab' => ['required', Rule::in([
                ApplicantTalentPoolQuery::TAB_INCOMPLETE,
                ApplicantTalentPoolQuery::TAB_UNVERIFIED,
                ApplicantTalentPoolQuery::TAB_VERIFIED,
            ])],
            'search' => ['nullable', 'string'],
            'sort' => ['nullable', Rule::in(['name', 'updated_at'])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        if ($validated['action'] !== 'pass' && ! $this->governanceService->isAvailable()) {
            return back()->with('error', 'Fitur governance applicant belum aktif karena migration database belum dijalankan. Jalankan php artisan migrate terlebih dahulu.');
        }

        $filters = [
            'tab' => (string) $validated['tab'],
            'search' => trim((string) ($validated['search'] ?? '')),
            'sort' => (string) ($validated['sort'] ?? 'updated_at'),
            'order' => (string) ($validated['order'] ?? 'desc'),
            'page' => 1,
        ];

        $profileIds = $this->talentPoolQuery->resolveSelectedProfileIds(
            $filters,
            (string) $validated['selection_scope'],
            (array) ($validated['profile_ids'] ?? [])
        );

        if ($profileIds === []) {
            return back()->with('error', 'Tidak ada applicant valid yang bisa diproses untuk bulk action ini.');
        }

        $meta = [
            'source' => 'hrd.applicants.bulk-action',
            'selection_scope' => $validated['selection_scope'],
            'filters' => $filters,
        ];

        if ($validated['action'] === 'pass') {
            return $this->handleBulkPass($profileIds, $request, $meta);
        }

        $stats = match ($validated['action']) {
            'reject' => $this->governanceService->bulkReject($profileIds, $request->user(), 'manual_reject_from_talent_pool', $meta),
            'blacklist' => $this->governanceService->bulkBlacklist($profileIds, $request->user(), 'manual_blacklist_from_talent_pool', $meta),
            'archive' => $this->governanceService->bulkArchive($profileIds, $request->user(), 'manual_archive_from_talent_pool', $meta),
            default => ['processed' => 0, 'succeeded' => 0, 'skipped' => 0, 'failed' => 0],
        };

        return back()->with(
            $stats['succeeded'] > 0 ? 'success' : 'error',
            sprintf(
                'Bulk %s selesai. Berhasil: %d, dilewati: %d, gagal: %d.',
                strtoupper((string) $validated['action']),
                $stats['succeeded'],
                $stats['skipped'],
                $stats['failed']
            )
        );
    }

    public function govern(Request $request, ApplicantProfile $profile): RedirectResponse
    {
        if (! $this->governanceService->isAvailable()) {
            return back()->with('error', 'Fitur governance applicant belum aktif karena migration database belum dijalankan. Jalankan php artisan migrate terlebih dahulu.');
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['reject', 'blacklist', 'archive', 'accept'])],
        ]);

        $meta = [
            'source' => 'hrd.applicants.govern',
            'selection_scope' => 'single',
        ];

        $ok = match ($validated['action']) {
            'reject'    => $this->governanceService->reject($profile, $request->user(), 'manual_reject_from_talent_pool', $meta),
            'blacklist' => $this->governanceService->blacklist($profile, $request->user(), 'manual_blacklist_from_talent_pool', $meta),
            'archive'   => $this->governanceService->archive($profile, $request->user(), 'manual_archive_from_talent_pool', $meta),
            'accept'    => $this->acceptApplicant($profile, $request->user()),
            default     => false,
        };

        if (! $ok) {
            return back()->with('error', 'Aksi tidak bisa dijalankan untuk applicant ini.');
        }

        $message = $validated['action'] === 'accept'
            ? 'Kandidat berhasil diterima sebagai karyawan probation.'
            : 'Aksi governance applicant berhasil dijalankan.';

        return redirect()->route('hrd.applicants.index')->with('success', $message);
    }

    public function export()
    {
        return back()->with('error', 'Fitur Export memerlukan library maatwebsite/excel.');
    }

    /**
     * @param array<int,int> $profileIds
     * @param array<string,mixed> $meta
     */
    private function handleBulkPass(array $profileIds, Request $request, array $meta): RedirectResponse
    {
        $stats = ['processed' => 0, 'succeeded' => 0, 'skipped' => 0, 'failed' => 0];

        ApplicantProfile::query()
            ->whereIn('id', $profileIds)
            ->with('user')
            ->get()
            ->each(function (ApplicantProfile $profile) use (&$stats, $request, $meta): void {
                $stats['processed']++;

                try {
                    $this->promoteApplicantToCandidateAction->execute($profile, $request->user(), $meta);
                    $stats['succeeded']++;
                } catch (ValidationException) {
                    $stats['skipped']++;
                } catch (Throwable) {
                    $stats['failed']++;
                }
            });

        return back()->with(
            $stats['succeeded'] > 0 ? 'success' : 'error',
            sprintf(
                'Bulk LOLOS ADMINISTRASI selesai. Berhasil: %d, dilewati: %d, gagal: %d.',
                $stats['succeeded'],
                $stats['skipped'],
                $stats['failed']
            )
        );
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    private function bulkActionsForTab(string $tab, bool $governanceAvailable = true): array
    {
        return match ($tab) {
            ApplicantTalentPoolQuery::TAB_INCOMPLETE => $governanceAvailable ? [
                ['value' => 'reject', 'label' => 'Reject selected'],
                ['value' => 'blacklist', 'label' => 'Blacklist selected'],
                ['value' => 'archive', 'label' => 'Archive selected'],
            ] : [],
            ApplicantTalentPoolQuery::TAB_UNVERIFIED => $governanceAvailable
                ? [
                    ['value' => 'pass', 'label' => 'Lolos Administrasi'],
                    ['value' => 'reject', 'label' => 'Tolak'],
                ]
                : [
                    ['value' => 'pass', 'label' => 'Lolos Administrasi'],
                ],
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $discResult
     * @param array<string,mixed> $computedResult
     * @return array<string,int>
     */
    private function resolveDiscScores(array $discResult, array $computedResult): array
    {
        $source = $discResult;

        if (empty($source)) {
            $source = data_get($computedResult, 'disc_result');
            if (! is_array($source)) {
                $source = $computedResult;
            }
        }

        return [
            'D' => max(0, (int) ($source['D'] ?? $source['d'] ?? 0)),
            'I' => max(0, (int) ($source['I'] ?? $source['i'] ?? 0)),
            'S' => max(0, (int) ($source['S'] ?? $source['s'] ?? 0)),
            'C' => max(0, (int) ($source['C'] ?? $source['c'] ?? 0)),
        ];
    }

    /**
     * @param array<string,int> $scores
     */
    private function resolveDominantDiscAxis(array $scores): ?string
    {
        if (empty($scores)) {
            return null;
        }

        $max = max($scores);
        if ($max <= 0) {
            return null;
        }

        foreach (['D', 'I', 'S', 'C'] as $axis) {
            if (($scores[$axis] ?? 0) === $max) {
                return $axis;
            }
        }

        return null;
    }

    private function isOfficialProbationProfileCompleted(?Candidate $candidate): bool
    {
        $employee = $candidate?->user?->employee;
        if (! $employee) {
            return false;
        }

        if (! in_array((string) $employee->status_employment, ['probation', 'contract', 'permanent'], true)) {
            return false;
        }

        return trim((string) ($employee->nokom ?? '')) !== ''
            && trim((string) ($employee->jabatan ?? '')) !== ''
            && ! empty($employee->join_date);
    }

    private function acceptApplicant(ApplicantProfile $profile, mixed $actor): bool
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($profile): void {
                // Email: coba user relation dulu, fallback ke personal_json
                $email = $profile->user?->email
                    ?? data_get($profile->personal_json, 'email')
                    ?? null;

                if (! $email) {
                    Log::warning('acceptApplicant: email tidak ditemukan', ['profile_id' => $profile->id]);
                    return;
                }

                // Nama dari accessor full_name, fallback ke user->name
                $fullName = $profile->full_name
                    ?? $profile->user?->name
                    ?? 'Tanpa Nama';

                // Phone dari personal_json
                $phone = data_get($profile->personal_json, 'phone')
                    ?? data_get($profile->personal_json, 'whatsapp')
                    ?? null;

                // NIK dari applicant profile (personal_json.ktp_number)
                $nikFromProfile = $profile->ktp_number ?: null;

                // Cari atau buat employee record
                $employee = \App\Models\Employee::where('email_private', $email)->first();
                if (! $employee) {
                    $employee = \App\Models\Employee::create([
                        'full_name'         => $fullName,
                        'email_private'     => $email,
                        'phone_number'      => $phone,
                        'nik'               => $nikFromProfile,
                        'join_date'         => now()->toDateString(),
                        'status_employment' => 'probation',
                    ]);
                } elseif (empty($employee->nik) && $nikFromProfile) {
                    // Employee record sudah ada duluan (matched by email) tapi NIK-nya kosong — sinkronkan juga
                    $employee->update(['nik' => $nikFromProfile]);
                }

                // Update user role dan link ke employee
                $user = $profile->user ?? \App\Models\User::where('email', $email)->first();
                if ($user) {
                    $user->role        = 'probation';
                    $user->employee_id = $employee->id;
                    $user->save();
                }

                // Tandai profile sebagai hired
                $profile->governance_status = 'hired';
                $profile->governed_at       = now();
                $profile->save();
            });

            return true;
        } catch (Throwable $e) {
            Log::error('acceptApplicant failed', [
                'profile_id' => $profile->id,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getPerPage(int $default = 20): int
    {
        $perPage = (int) request('per_page', $default);
        return in_array($perPage, [10, 15, 20, 50, 100, 200, 9999]) ? $perPage : $default;
    }
}
