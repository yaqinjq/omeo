<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCandidateAssessmentRequest;
use App\Models\AssessmentForm;
use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\FormAssignment;
use App\Services\CandidateBlacklistService;
use App\Services\CandidatePromotionService;
use App\Services\CandidateWorkflowService;
use App\Services\CandidateTestActivationService;
use Illuminate\Contracts\Support\Responsable;
use Spatie\LaravelPdf\Facades\Pdf;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class CandidateController extends Controller
{
    private const TAB_NEW = 'new';
    private const TAB_ACTIVE = 'active';
    private const TAB_TESTED = 'tested';
    private const TAB_DONE = 'done';
    private const TAB_REJECTED = 'rejected';
    private const TAB_BLOCKED = 'blocked';

    private const FINAL_CANDIDATE_STATUSES = [
        Candidate::STATUS_ACCEPTED,
        Candidate::STATUS_REJECTED,
        Candidate::STATUS_BLOCKED,
    ];

    private const FINAL_ASSESSMENT_STATUSES = [
        CandidateAssessment::STATUS_PASSED,
        CandidateAssessment::STATUS_RESERVE,
        CandidateAssessment::STATUS_REJECTED,
        CandidateAssessment::STATUS_BLOCKED,
    ];

    public function __construct(
        private readonly CandidateTestActivationService $candidateTestActivationService,
    ) {
    }

    public function index(Request $request)
    {
        $allowedTabs = [self::TAB_NEW, self::TAB_ACTIVE, self::TAB_TESTED, self::TAB_DONE, self::TAB_REJECTED, self::TAB_BLOCKED];
        $tab = (string) $request->query('tab', self::TAB_NEW);
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = self::TAB_NEW;
        }

        $search = trim((string) $request->query('search', ''));
        $sort = $this->normalizeSort((string) $request->query('sort', $tab === self::TAB_TESTED ? 'latest_test' : 'latest'));
        $testType = $this->normalizeTestType((string) $request->query('test_type', 'all'));

        $listQuery = Candidate::query()
            ->select('candidates.*')
            ->with([
                'assessment:id,candidate_id,status,iq_score,disc_result,interview_score,updated_at',
                'formAssignments:id,candidate_id,form_id,status,opened_at,expires_at,closed_at,created_by,created_at,updated_at',
                'formAssignments.form:id,name,code,type',
                'formAssignments.attempt:id,form_assignment_id,started_at,submitted_at,time_spent_seconds,computed_result',
            ])
            ->withCount([
                'formAssignments as opened_assignments_count' => fn ($query) => $query->where('status', FormAssignment::STATUS_OPENED),
                'formAssignments as tested_assignments_count' => fn ($query) => $query->whereIn('status', [FormAssignment::STATUS_SUBMITTED, FormAssignment::STATUS_EXPIRED]),
            ])
            ->selectSub($this->latestTestActivitySubquery(), 'latest_test_activity_at');

        $this->applySearchFilter($listQuery, $search);
        $this->applyTabFilter($listQuery, $tab);
        $this->applyTestTypeFilter($listQuery, $tab, $testType);
        $this->applyListSorting($listQuery, $tab, $sort);

        $candidates = $listQuery->paginate(15)->withQueryString();

        $workflowService = app(CandidateWorkflowService::class);
        $candidates->getCollection()->transform(function (Candidate $candidate) use ($workflowService) {
            $candidate->setAttribute('can_restore', $workflowService->canRestore($candidate));
            $candidate->setAttribute('restore_deadline', $workflowService->restoreDeadline($candidate));

            $profile = $this->findApplicantProfileForCandidate($candidate);
            foreach ($this->buildCandidateApplicationSnapshot($candidate, $profile) as $key => $value) {
                $candidate->setAttribute($key, $value);
            }

            return $candidate;
        });

        $counts = [];
        foreach ($allowedTabs as $tabKey) {
            $countQuery = Candidate::query();
            $this->applySearchFilter($countQuery, $search);
            $this->applyTabFilter($countQuery, $tabKey);
            $counts[$tabKey] = $countQuery->count();
        }

        $formGroups = $this->buildRecruitmentFormGroups();
        $testTypeOptions = ['all' => 'Semua Test'] + AssessmentForm::assignableTypes();

        return view('candidates.index', compact('candidates', 'tab', 'counts', 'search', 'sort', 'testType', 'formGroups', 'testTypeOptions'));
    }

    public function create()
    {
        return view('candidates.create');
    }

    public function store(Request $request, CandidateBlacklistService $blacklistService)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'nik' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        if ($blacklistService->findMatches($data['nik'] ?? null, $data['email'] ?? null, $data['phone'] ?? null)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'nik' => 'Kandidat teridentifikasi dalam blacklist (NIK/email/phone). Registrasi kandidat ditolak.',
            ]);
        }

        $data['status'] = Candidate::STATUS_APPLIED;
        $data['applied_at'] = now();
        Candidate::create($data);
        return redirect()->route('candidates.index')->with('success', 'Kandidat ditambahkan.');
    }

    public function edit(Candidate $candidate)
    {
        return view('candidates.edit', compact('candidate'));
    }

    public function update(Request $request, Candidate $candidate, CandidateBlacklistService $blacklistService)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'nik' => 'nullable|string|max:50',
            'status' => ['required', Rule::in(Candidate::EDITABLE_STATUSES)],
            'notes' => 'nullable|string',
        ]);

        $matches = $blacklistService->findMatches($data['nik'] ?? null, $data['email'] ?? null, $data['phone'] ?? null);
        if ($matches->isNotEmpty() && ($data['status'] ?? null) !== Candidate::STATUS_BLOCKED) {
            throw ValidationException::withMessages([
                'nik' => 'Identitas kandidat masuk blacklist, hanya status blocked yang diperbolehkan.',
            ]);
        }

        if (($data['status'] ?? null) === Candidate::STATUS_BLOCKED) {
            $data['blocked_at'] = now();
            $data['accepted_at'] = null;
            $data['rejected_at'] = null;
            $candidate->fill($data)->save();
            $blacklistService->upsertBlockedCandidate(
                $candidate,
                reason: 'blocked_by_hrd',
                lastAppliedPosition: null,
                meta: ['actor_user_id' => $request->user()?->id, 'source' => 'candidate_update'],
                source: 'candidate_update'
            );
        } elseif (($data['status'] ?? null) === Candidate::STATUS_REJECTED) {
            $data['rejected_at'] = now();
            $data['accepted_at'] = null;
            $data['blocked_at'] = null;
            $candidate->update($data);
            $blacklistService->removeBlockedCandidate($candidate);
        } elseif (($data['status'] ?? null) === Candidate::STATUS_ACCEPTED) {
            $data['accepted_at'] = now();
            $data['rejected_at'] = null;
            $data['blocked_at'] = null;
            $candidate->update($data);
            $blacklistService->removeBlockedCandidate($candidate);
        } else {
            $data['accepted_at'] = null;
            $data['rejected_at'] = null;
            $data['blocked_at'] = null;
            $candidate->update($data);
            $blacklistService->removeBlockedCandidate($candidate);
        }

        return redirect()->route('candidates.index')->with('success', 'Kandidat diupdate.');
    }

    public function destroy(Candidate $candidate)
    {
        $candidate->delete();
        return redirect()->route('candidates.index')->with('success', 'Kandidat dihapus.');
    }

    public function show(Candidate $candidate)
    {
        $candidate->load([
            'assessment',
            'formAssignments.form',
            'formAssignments.attempt',
            'activityLogs.actor',
        ]);

        $forms = AssessmentForm::query()
            ->whereIn('type', array_keys(AssessmentForm::assignableTypes()))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('type');

        $workflowService = app(CandidateWorkflowService::class);
        $canRestore = $workflowService->canRestore($candidate);
        $restoreDeadline = $workflowService->restoreDeadline($candidate);
        $assignableTypes = AssessmentForm::assignableTypes();
        $profile = $this->findApplicantProfileForCandidate($candidate);

        foreach ($this->buildCandidateApplicationSnapshot($candidate, $profile) as $key => $value) {
            $candidate->setAttribute($key, $value);
        }

        return view('candidates.show', compact('candidate', 'forms', 'canRestore', 'restoreDeadline', 'assignableTypes'));
    }

    public function bulkActivate(Request $request)
    {
        return $this->bulkActivateTests($request);
    }

    public function bulkStatus(Request $request, CandidateWorkflowService $workflowService)
    {
        $validated = $request->validate([
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['integer', 'exists:candidates,id'],
            'action' => ['required', Rule::in(['accept', 'reject', 'block', 'restore'])],
        ]);

        $candidateIds = collect($validated['candidate_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $stats = $workflowService->bulkUpdateStatus($candidateIds, (string) $validated['action'], $request->user());

        $message = sprintf(
            'Bulk %s selesai. Berhasil: %d, dilewati: %d, gagal: %d.',
            strtoupper((string) $validated['action']),
            $stats['succeeded'],
            $stats['skipped'],
            $stats['failed']
        );

        return back()->with($stats['succeeded'] > 0 ? 'success' : 'error', $message);
    }

    public function bulkActivateTests(Request $request)
    {
        $validated = $request->validate([
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['integer', 'exists:candidates,id'],
            'form_ids' => ['nullable', 'array'],
            'form_ids.*' => ['integer', 'exists:forms,id'],
            'action' => ['nullable', Rule::in(['iq', 'disc', 'both'])],
            'iq_form_id' => ['nullable', 'integer', 'exists:forms,id'],
            'disc_form_id' => ['nullable', 'integer', 'exists:forms,id'],
        ]);

        $forms = $this->candidateTestActivationService->resolveFormsFromPayload($validated);

        $candidateIds = collect($validated['candidate_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $stats = $this->candidateTestActivationService->activateForCandidates(
            $candidateIds,
            $forms,
            $request->user(),
            [
                'source' => 'candidates.bulk-activate-tests',
                'selection_scope' => 'bulk',
            ]
        );

        Log::info('Candidate tests bulk activated with templates', [
            'candidate_count' => count($candidateIds),
            'form_ids' => $forms->pluck('id')->all(),
            'pairs_processed' => $stats['processed_pairs'],
            'pairs_opened_or_updated' => $stats['opened_or_updated'],
            'pairs_skipped_completed' => $stats['skipped_completed'],
            'pairs_failed' => $stats['failed'],
            'opened_by' => $request->user()->id,
        ]);

        return back()->with(
            $stats['opened_or_updated'] > 0 ? 'success' : 'error',
            "Bulk aktivasi selesai. Dibuka/diupdate: {$stats['opened_or_updated']}, dilewati (sudah selesai): {$stats['skipped_completed']}, gagal: {$stats['failed']}."
        );
    }

    public function accept(Candidate $candidate, CandidatePromotionService $promotionService)
    {
        try {
            $this->relaxExportRuntimeBudget();

            $employee = DB::transaction(function () use ($candidate, $promotionService) {
                $candidate->update([
                    'status' => Candidate::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                    'rejected_at' => null,
                    'blocked_at' => null,
                ]);

                CandidateAssessment::updateOrCreate(
                    ['candidate_id' => $candidate->id],
                    ['status' => CandidateAssessment::STATUS_PASSED]
                );

                return $promotionService->promoteCandidateToEmployee($candidate->fresh('user'));
            });
        } catch (Throwable $e) {
            Log::error('Candidate accept failed', [
                'candidate_id' => $candidate->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Gagal menerima kandidat. Silakan cek konfigurasi data karyawan.');
        }

        Log::info('Candidate accepted and promoted', [
            'candidate_id' => $candidate->id,
            'employee_id' => $employee->id,
        ]);

        return back()->with('success', 'Kandidat diterima dan dipromosikan ke Employee Probation.');
    }

    public function reject(Candidate $candidate, CandidateWorkflowService $workflowService)
    {
        $workflowService->reject($candidate, request()->user(), ['source' => 'candidate_reject_action']);

        Log::info('Candidate rejected', ['candidate_id' => $candidate->id]);
        return back()->with('success', 'Kandidat ditolak. Data tetap disimpan untuk retensi fase berikutnya.');
    }

    public function block(Candidate $candidate, Request $request, CandidateWorkflowService $workflowService)
    {
        $workflowService->block($candidate, $request->user(), ['source' => 'candidate_block_action']);

        Log::info('Candidate blocked', ['candidate_id' => $candidate->id]);
        return back()->with('success', 'Kandidat diblokir. Data tetap disimpan untuk retensi fase berikutnya.');
    }

    public function restore(Candidate $candidate, Request $request, CandidateWorkflowService $workflowService)
    {
        if (! $workflowService->restore($candidate, $request->user(), ['source' => 'candidate_restore_action'])) {
            return back()->with('error', 'Restore tidak bisa dilakukan karena masa retensi kandidat sudah lewat atau status kandidat tidak mendukung restore.');
        }

        return back()->with('success', 'Kandidat berhasil direstore ke pipeline recruitment.');
    }

    public function updateAssessment(UpdateCandidateAssessmentRequest $request, Candidate $candidate)
    {
        $data = $request->validated();

        $discHasValue = $request->filled('disc_d') || $request->filled('disc_i') || $request->filled('disc_s') || $request->filled('disc_c');
        $discResult = $discHasValue ? [
            'D' => (int) ($data['disc_d'] ?? 0),
            'I' => (int) ($data['disc_i'] ?? 0),
            'S' => (int) ($data['disc_s'] ?? 0),
            'C' => (int) ($data['disc_c'] ?? 0),
            'graphs' => null,
            'summary' => null,
        ] : null;

        CandidateAssessment::updateOrCreate(
            ['candidate_id' => $candidate->id],
            [
                'iq_score' => $data['iq_score'] ?? null,
                'disc_result' => $discResult,
                'interview_score' => $data['interview_score'] ?? null,
                'interview_notes' => $data['interview_notes'] ?? null,
                'status' => $data['status'],
            ]
        );

        Log::info('Candidate assessment manual updated', [
            'candidate_id' => $candidate->id,
            'updated_by' => $request->user()->id,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Penilaian kandidat berhasil disimpan.');
    }

    public function openTest(Request $request, Candidate $candidate)
    {
        $validated = $request->validate([
            'form_id' => ['nullable', 'integer', 'exists:forms,id'],
            'form_ids' => ['nullable', 'array'],
            'form_ids.*' => ['integer', 'exists:forms,id'],
        ]);

        $forms = $this->candidateTestActivationService->resolveFormsFromPayload($validated);
        $stats = $this->candidateTestActivationService->activateForCandidate(
            $candidate,
            $forms,
            $request->user(),
            [
                'source' => 'candidates.tests.open',
                'selection_scope' => 'single',
            ]
        );

        Log::info('Candidate test opened', [
            'candidate_id' => $candidate->id,
            'form_ids' => $forms->pluck('id')->all(),
            'opened_or_updated' => $stats['opened_or_updated'],
            'skipped_completed' => $stats['skipped_completed'],
            'failed' => $stats['failed'],
            'opened_by' => $request->user()->id,
        ]);

        if ($stats['opened_or_updated'] === 0) {
            return back()->with('error', 'Semua test yang dipilih sudah selesai. Gunakan reset attempt jika ingin membuka ulang.');
        }

        $message = $stats['opened_or_updated'] > 1
            ? "{$stats['opened_or_updated']} test berhasil dibuka untuk kandidat."
            : 'Test berhasil dibuka untuk kandidat.';

        return back()->with('success', $message);
    }

    public function lockTest(Request $request, Candidate $candidate, FormAssignment $assignment)
    {
        if ($assignment->candidate_id !== $candidate->id) {
            abort(404);
        }

        if ($assignment->status === FormAssignment::STATUS_SUBMITTED) {
            return back()->with('error', 'Test yang sudah submit tidak bisa dikunci.');
        }

        $assignment->update([
            'status' => FormAssignment::STATUS_LOCKED,
            'opened_at' => null,
            'expires_at' => null,
            'closed_at' => null,
        ]);

        return back()->with('success', 'Test berhasil dikunci.');
    }

    public function resetTest(Request $request, Candidate $candidate, FormAssignment $assignment)
    {
        if ($assignment->candidate_id !== $candidate->id) {
            abort(404);
        }

        DB::transaction(function () use ($assignment): void {
            $attempt = $assignment->attempt;
            if ($attempt) {
                $attempt->answers()->delete();
                $attempt->delete();
            }

            $assignment->update([
                'status' => FormAssignment::STATUS_LOCKED,
                'opened_at' => null,
                'expires_at' => null,
                'closed_at' => null,
            ]);
        });

        Log::info('Candidate test reset', [
            'candidate_id' => $candidate->id,
            'assignment_id' => $assignment->id,
            'reset_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Attempt test berhasil di-reset.');
    }

    public function applicantProfile(Candidate $candidate)
    {
        $profile = $this->findApplicantProfileForCandidate($candidate);

        if (!$profile) {
            return back()->with('error', 'Profil pelamar belum ditemukan.');
        }

        return redirect()->route('hrd.applicants.show', $profile);
    }

    // Fungsi untuk Export (Download file PDF)
    public function exportPdf(Request $request, Candidate $candidate): Responsable|RedirectResponse
    {
        try {
            $fileName = $this->buildCandidateProfilePdfFileName($candidate);

            return $this->applyPdfResponseHeaders(
                $this->buildCandidateProfilePdf($candidate)->download($fileName),
                $fileName,
                'attachment'
            );
        } catch (Throwable $e) {
            Log::error('Candidate export PDF failed', [
                'candidate_id' => $candidate->id,
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 3000),
                'exported_by' => $request->user()?->id,
            ]);

            return back()->with('error', 'Gagal mengekspor PDF profil kandidat. Silakan coba lagi.');
        }
    }

    public function previewPage(Request $request, Candidate $candidate)
    {
        try {
            $candidate->load([
                'user',
                'assessment',
                'formAssignments.form',
                'formAssignments.attempt',
            ]);

            $profile = $this->findApplicantProfileForCandidate($candidate);

            return view('hrd.exports.candidate_profile_preview', [
                'candidate' => $candidate,
                'profile' => $profile,
                'documentAssets' => $this->buildDocumentAssets($profile),
                'previewUrl' => route('candidates.preview-pdf', $candidate),
                'downloadUrl' => route('candidates.export-pdf', $candidate),
                'downloadCvUrl' => route('candidates.export-cv', $candidate),
                'downloadPackageUrl' => route('candidates.export-package', $candidate),
            ]);
        } catch (Throwable $e) {
            Log::error('Candidate preview page failed', [
                'candidate_id' => $candidate->id,
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 3000),
                'preview_by' => $request->user()?->id,
            ]);

            return back()->with('error', 'Gagal memuat halaman preview profil kandidat. Silakan coba lagi.');
        }
    }

    // Fungsi untuk Preview
    public function preview(Request $request, Candidate $candidate): Responsable|RedirectResponse
    {
        try {
            $fileName = $this->buildCandidateProfilePdfFileName($candidate);

            return $this->applyPdfResponseHeaders(
                $this->buildCandidateProfilePdf($candidate)->inline($fileName),
                $fileName,
                'inline'
            );
        } catch (Throwable $e) {
            Log::error('Candidate preview PDF failed', [
                'candidate_id' => $candidate->id,
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 3000),
                'preview_by' => $request->user()?->id,
            ]);

            return back()->with('error', 'Gagal memuat preview PDF profil kandidat. Silakan coba lagi.');
        }
    }

    public function downloadOriginalCv(Request $request, Candidate $candidate): StreamedResponse|RedirectResponse
    {
        try {
            $profile = $this->findApplicantProfileForCandidate($candidate);
            $cvPath = trim((string) $profile?->cv_path);

            if ($cvPath === '' || ! Storage::disk('public')->exists($cvPath)) {
                return back()->with('error', 'CV asli kandidat belum tersedia untuk diunduh.');
            }

            $extension = strtolower((string) pathinfo($cvPath, PATHINFO_EXTENSION)) ?: 'pdf';
            $downloadName = $this->buildCandidateDocumentFileName($candidate, 'cv_asli', $extension);

            return Storage::disk('public')->download($cvPath, $downloadName, [
                'Content-Type' => Storage::disk('public')->mimeType($cvPath) ?: 'application/octet-stream',
                'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $downloadName, $downloadName),
            ]);
        } catch (Throwable $e) {
            Log::error('Candidate CV download failed', [
                'candidate_id' => $candidate->id,
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 3000),
                'downloaded_by' => $request->user()?->id,
            ]);

            return back()->with('error', 'Gagal mengunduh CV asli kandidat. Silakan coba lagi.');
        }
    }

    public function downloadPackage(Request $request, Candidate $candidate): BinaryFileResponse|RedirectResponse
    {
        try {
            $profile = $this->findApplicantProfileForCandidate($candidate);
            $zipPath = $this->buildCandidatePackageZip($candidate, $profile);

            return response()->download(
                $zipPath['path'],
                $zipPath['download_name'],
                [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $zipPath['download_name'], $zipPath['download_name']),
                ]
            )->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            Log::error('Candidate package download failed', [
                'candidate_id' => $candidate->id,
                'error' => $e->getMessage(),
                'trace' => Str::limit($e->getTraceAsString(), 3000),
                'downloaded_by' => $request->user()?->id,
            ]);

            return back()->with('error', 'Gagal mengunduh paket kandidat. Silakan coba lagi.');
        }
    }

    private function buildCandidateProfilePdf(Candidate $candidate)
    {
        $this->relaxExportRuntimeBudget();

        $candidate->load([
            'user',
            'assessment',
            'formAssignments.form',
            'formAssignments.attempt',
        ]);

        $profile = $this->findApplicantProfileForCandidate($candidate);
        $assessment = $candidate->assessment;
        $iqAssignment = $this->latestAssignmentByType($candidate, AssessmentForm::TYPE_IQ);
        $discAssignment = $this->latestAssignmentByType($candidate, AssessmentForm::TYPE_DISC);

        $discScores = $this->resolveDiscScores(
            $assessment?->disc_result,
            is_array($discAssignment?->attempt?->computed_result) ? $discAssignment->attempt->computed_result : []
        );
        $dominantAxis = $this->resolveDominantDiscAxis($discScores);

        return Pdf::view('hrd.exports.candidate_profile_pdf', [
            'candidate' => $candidate,
            'profile' => $profile,
            'assessment' => $assessment,
            'iqAssignment' => $iqAssignment,
            'discAssignment' => $discAssignment,
            'discScores' => $discScores,
            'discDominantAxis' => $dominantAxis,
            'discInterpretation' => $this->buildDiscInterpretation($dominantAxis),
            'documentAssets' => $this->buildDocumentAssets($profile),
            'appliedPosition' => $this->resolveAppliedPosition($candidate, $profile),
            'appliedOutlet' => $this->resolveAppliedOutlet($candidate, $profile),
            'generatedAt' => now(),
        ])
            ->format('a4')
            ->name($this->buildCandidateProfilePdfFileName($candidate));
    }

    private function findApplicantProfileForCandidate(Candidate $candidate): ?ApplicantProfile
    {
        $profile = ApplicantProfile::query()
            ->with('user')
            ->when($candidate->user_id, fn ($query) => $query->where('user_id', $candidate->user_id))
            ->first();

        if (! $profile && $candidate->email) {
            $email = trim((string) $candidate->email);
            $emailLower = mb_strtolower($email);
            $emailVariants = collect([$email, $emailLower])->filter()->unique()->values()->all();

            $profile = ApplicantProfile::query()
                ->with('user')
                ->where(function ($query) use ($emailVariants, $emailLower) {
                    $query->whereIn('personal_json->email', $emailVariants)
                        ->orWhereHas('user', function ($userQuery) use ($emailLower) {
                            $userQuery->whereRaw('LOWER(email) = ?', [$emailLower]);
                        });
                })
                ->first();
        }

        if (! $profile && $candidate->nik) {
            $profile = ApplicantProfile::query()
                ->with('user')
                ->where('personal_json->ktp_number', trim((string) $candidate->nik))
                ->first();
        }

        return $profile;
    }

    private function latestAssignmentByType(Candidate $candidate, string $type): ?FormAssignment
    {
        return $candidate->formAssignments
            ->filter(function (FormAssignment $assignment) use ($type): bool {
                return $assignment->form?->type === $type && $assignment->attempt !== null;
            })
            ->sortByDesc(function (FormAssignment $assignment): int {
                $attempt = $assignment->attempt;
                return $attempt?->submitted_at?->timestamp
                    ?? $attempt?->started_at?->timestamp
                    ?? $assignment->updated_at?->timestamp
                    ?? 0;
            })
            ->first();
    }

    /**
     * @param array<string,mixed>|null $discResult
     * @param array<string,mixed> $computedResult
     * @return array<string,int>
     */
    private function resolveDiscScores(?array $discResult, array $computedResult): array
    {
        $source = $discResult;

        if (empty($source)) {
            $source = data_get($computedResult, 'disc_result');
            if (!is_array($source)) {
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

    private function resolveDominantDiscAxis(array $discScores): ?string
    {
        if (empty($discScores)) {
            return null;
        }

        $max = max($discScores);
        if ($max <= 0) {
            return null;
        }

        foreach (['D', 'I', 'S', 'C'] as $axis) {
            if (($discScores[$axis] ?? 0) === $max) {
                return $axis;
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function buildDiscInterpretation(?string $dominantAxis): array
    {
        return match ($dominantAxis) {
            'D' => [
                'Cenderung tegas dan cepat mengambil keputusan saat menghadapi target.',
                'Nyaman memimpin arah kerja dan bertindak langsung pada prioritas utama.',
                'Menyukai tantangan serta lingkungan kerja yang dinamis dan kompetitif.',
                'Perlu keseimbangan pada aspek ketelitian dan kesabaran dalam proses detail.',
            ],
            'I' => [
                'Komunikatif, mudah membangun relasi, dan aktif memengaruhi orang lain.',
                'Kuat pada peran kolaboratif, presentasi, dan interaksi dengan tim.',
                'Cenderung optimis dan antusias terhadap ide baru.',
                'Perlu menjaga konsistensi tindak lanjut agar eksekusi tetap terukur.',
            ],
            'S' => [
                'Stabil, suportif, dan nyaman bekerja dengan ritme yang konsisten.',
                'Menunjukkan kesabaran serta kepedulian dalam koordinasi tim.',
                'Cocok pada peran yang menuntut ketekunan dan kesinambungan proses.',
                'Perlu dorongan saat menghadapi perubahan mendadak atau keputusan cepat.',
            ],
            'C' => [
                'Teliti, sistematis, dan berorientasi pada standar kualitas kerja.',
                'Kuat dalam analisis data, kepatuhan prosedur, dan akurasi dokumen.',
                'Cenderung berhati-hati sebelum memutuskan agar risiko tetap terkendali.',
                'Perlu menyeimbangkan kecepatan agar tidak tertahan oleh over-analisis.',
            ],
            default => [
                'Hasil DISC belum tersedia atau belum cukup data untuk interpretasi.',
            ],
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDocumentAssets(?ApplicantProfile $profile): array
    {
        return [
            'photo' => $this->buildDocumentAsset($profile?->photo_path),
            'ktp' => $this->buildDocumentAsset($profile?->ktp_path),
            'cv' => $this->buildDocumentAsset($profile?->cv_path),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDocumentAsset(?string $relativePath): array
    {
        $path = trim((string) $relativePath);
        if ($path === '') {
            return [
                'available' => false,
                'configured' => false,
                'url' => null,
                'name' => null,
                'is_image' => false,
                'is_pdf' => false,
                'thumbnail_data_uri' => null,
                'pdf_preview_pages' => [],
                'pdf_preview_total_pages' => 0,
                'preview_message' => null,
            ];
        }

        $url = asset('storage/' . ltrim($path, '/'));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
        $isPdf = $extension === 'pdf';
        $existsInStorage = Storage::disk('public')->exists($path);

        if (! $existsInStorage) {
            return [
                'available' => false,
                'configured' => true,
                'url' => $url,
                'name' => basename($path),
                'is_image' => $isImage,
                'is_pdf' => $isPdf,
                'thumbnail_data_uri' => null,
                'pdf_preview_pages' => [],
                'pdf_preview_total_pages' => 0,
                'preview_message' => 'File dokumen tercatat di database, tetapi file fisiknya tidak ditemukan di storage server.',
            ];
        }

        $thumbnailDataUri = null;
        if ($isImage) {
            $absolutePath = Storage::disk('public')->path($path);
            $content = @file_get_contents($absolutePath);
            if ($content !== false) {
                $mime = match ($extension) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => 'image/jpeg',
                };
                $thumbnailDataUri = 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        }

        $pdfPreviewPages = [];
        $pdfPreviewTotalPages = 0;
        $previewMessage = null;
        if ($isPdf) {
            $absolutePath = Storage::disk('public')->path($path);
            $preview = $this->renderPdfPagesToDataUris(
                $absolutePath,
                (int) config('hr_export.cv_pdf_preview_max_pages', 2)
            );
            $pdfPreviewPages = Arr::get($preview, 'pages', []);
            $pdfPreviewTotalPages = (int) Arr::get($preview, 'total_pages', 0);
            $previewMessage = Arr::get($preview, 'message');
        }

        return [
            'available' => true,
            'configured' => true,
            'url' => $url,
            'path' => $path,
            'name' => basename($path),
            'is_image' => $isImage,
            'is_pdf' => $isPdf,
            'thumbnail_data_uri' => $thumbnailDataUri,
            'pdf_preview_pages' => $pdfPreviewPages,
            'pdf_preview_total_pages' => $pdfPreviewTotalPages,
            'preview_message' => $previewMessage,
        ];
    }

    private function applyPdfResponseHeaders(mixed $pdfBuilder, string $fileName, string $disposition): mixed
    {
        $fallbackBase = Str::slug(Str::ascii(pathinfo($fileName, PATHINFO_FILENAME)), '_');
        $fallbackName = ($fallbackBase !== '' ? $fallbackBase : 'candidate_profile') . '.pdf';

        return $pdfBuilder->headers([
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $fileName, $fallbackName),
            'X-Download-Filename' => $fileName,
        ]);
    }

    private function buildCandidateProfilePdfFileName(Candidate $candidate): string
    {
        return $this->buildCandidateDocumentFileName($candidate, 'profil_kandidat', 'pdf');
    }

    private function buildCandidateDocumentFileName(Candidate $candidate, string $label, string $extension): string
    {
        $extension = ltrim(strtolower($extension), '.');

        return Str::slug($label, '_')
            . '_'
            . Str::slug((string) ($candidate->full_name ?: 'kandidat'), '_')
            . '_'
            . now()->format('Ymd_His')
            . '.'
            . $extension;
    }

    /**
     * @return array{path: string, download_name: string}
     */
    private function buildCandidatePackageZip(Candidate $candidate, ?ApplicantProfile $profile): array
    {
        $downloadName = $this->buildCandidateDocumentFileName($candidate, 'paket_kandidat', 'zip');
        $absolutePath = tempnam(sys_get_temp_dir(), 'candidate-package-');

        if ($absolutePath === false) {
            throw new \RuntimeException('Gagal menyiapkan file sementara untuk paket kandidat.');
        }

        $zipAbsolutePath = $absolutePath . '.zip';
        @rename($absolutePath, $zipAbsolutePath);

        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat arsip paket kandidat.');
        }

        $profilePdfContent = base64_decode($this->buildCandidateProfilePdf($candidate)->base64(), true);
        if ($profilePdfContent === false) {
            $zip->close();
            throw new \RuntimeException('Gagal menyiapkan PDF profil kandidat untuk paket dokumen.');
        }

        $zip->addFromString($this->buildCandidateProfilePdfFileName($candidate), $profilePdfContent);

        foreach ($this->collectCandidatePackageDocuments($profile) as $document) {
            if (! Storage::disk('public')->exists($document['path'])) {
                continue;
            }

            $zip->addFile(Storage::disk('public')->path($document['path']), $document['archive_name']);
        }

        $zip->close();

        return [
            'path' => $zipAbsolutePath,
            'download_name' => $downloadName,
        ];
    }

    /**
     * @return array<int,array{path: string, archive_name: string}>
     */
    private function collectCandidatePackageDocuments(?ApplicantProfile $profile): array
    {
        $documents = [];

        foreach ([
            'pas_foto' => $profile?->photo_path,
            'scan_ktp' => $profile?->ktp_path,
            'cv_asli' => $profile?->cv_path,
        ] as $label => $path) {
            $cleanPath = trim((string) $path);
            if ($cleanPath === '') {
                continue;
            }

            $extension = strtolower((string) pathinfo($cleanPath, PATHINFO_EXTENSION));
            $documents[] = [
                'path' => $cleanPath,
                'archive_name' => Str::slug($label, '_') . ($extension !== '' ? '.' . $extension : ''),
            ];
        }

        return $documents;
    }

    /**
     * Convert PDF pages into data URI images when Imagick is available.
     *
     * @return array{pages: array<int,string>, total_pages: int, message: ?string}
     */
    private function renderPdfPagesToDataUris(string $absolutePdfPath, int $maxPages = 10): array
    {
        if (!config('hr_export.cv_pdf_preview_enabled', true)) {
            return [
                'pages' => [],
                'total_pages' => 0,
                'message' => 'Preview halaman CV PDF dinonaktifkan di konfigurasi server ini.',
            ];
        }

        $maxFileBytes = (int) config('hr_export.cv_pdf_preview_max_file_bytes', 0);
        if ($maxFileBytes > 0) {
            $fileSize = @filesize($absolutePdfPath);
            if (is_int($fileSize) && $fileSize > $maxFileBytes) {
                return [
                    'pages' => [],
                    'total_pages' => 0,
                    'message' => 'Preview CV dilewati karena ukuran file PDF terlalu besar untuk render server.',
                ];
            }
        }

        if (!extension_loaded('imagick')) {
            return [
                'pages' => [],
                'total_pages' => 0,
                'message' => 'Preview halaman CV PDF belum bisa dirender di server ini. Aktifkan ekstensi Imagick + Ghostscript untuk menampilkan halaman CV sebagai gambar.',
            ];
        }

        try {
            $safePath = str_replace('\\', '/', $absolutePdfPath);
            $ping = new \Imagick();
            $ping->pingImage($safePath);
            $totalPages = max(0, (int) $ping->getNumberImages());
            $ping->clear();
            $ping->destroy();

            if ($totalPages === 0) {
                return [
                    'pages' => [],
                    'total_pages' => 0,
                    'message' => 'File CV PDF terdeteksi, tetapi jumlah halamannya tidak bisa dibaca oleh server.',
                ];
            }

            $pages = [];
            $limit = min($totalPages, max(1, $maxPages));
            $resolution = max(72, (int) config('hr_export.cv_pdf_preview_resolution', 96));
            for ($index = 0; $index < $limit; $index++) {
                $page = new \Imagick();
                $page->setResolution($resolution, $resolution);
                $page->readImage($safePath . '[' . $index . ']');
                $page->setImageFormat('jpeg');
                $page->setImageCompressionQuality((int) config('hr_export.cv_pdf_preview_quality', 70));
                $pages[] = 'data:image/jpeg;base64,' . base64_encode($page->getImagesBlob());
                $page->clear();
                $page->destroy();
            }

            return [
                'pages' => $pages,
                'total_pages' => $totalPages,
                'message' => $totalPages > $limit
                    ? 'Preview CV dibatasi untuk menjaga performa export PDF di server.'
                    : null,
            ];
        } catch (Throwable $e) {
            Log::warning('Gagal render preview CV PDF', [
                'pdf_path' => $absolutePdfPath,
                'error' => $e->getMessage(),
                'imagick_loaded' => extension_loaded('imagick'),
            ]);

            return [
                'pages' => [],
                'total_pages' => 0,
                'message' => 'Preview halaman CV PDF gagal dirender di server. File CV tetap tercatat, tetapi halaman PDF tidak diubah menjadi gambar.',
            ];
        }
    }

    private function relaxExportRuntimeBudget(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('hr_export.max_execution_time', 120));
        }

        $memoryLimit = trim((string) config('hr_export.memory_limit', ''));
        if ($memoryLimit !== '') {
            @ini_set('memory_limit', $memoryLimit);
        }
    }

    private function resolveAppliedPosition(Candidate $candidate, ?ApplicantProfile $profile): string
    {
        return trim((string) (
            $candidate->applied_position_name
            ?? $profile?->applied_position_name
            ?? '-'
        ));
    }

    private function resolveAppliedOutlet(Candidate $candidate, ?ApplicantProfile $profile): string
    {
        return trim((string) (
            $candidate->applied_outlet_name
            ?? $profile?->applied_outlet_name
            ?? '-'
        ));
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCandidateApplicationSnapshot(Candidate $candidate, ?ApplicantProfile $profile): array
    {
        return [
            'applicant_photo_path' => data_get($profile?->personal_json, 'photo_path'),
            'applicant_profile_id' => $profile?->id,
            'application_position_name' => $this->resolveCandidateAppliedPositionName($candidate, $profile),
            'application_department_name' => $this->resolveCandidateAppliedDepartmentName($candidate, $profile),
            'application_outlet_name' => $this->resolveCandidateAppliedOutletName($candidate, $profile),
        ];
    }

    private function resolveCandidateAppliedPositionName(Candidate $candidate, ?ApplicantProfile $profile): string
    {
        $value = trim((string) ($candidate->applied_position_name ?? $profile?->applied_position_name ?? ''));

        return $value !== '' ? $value : '-';
    }

    private function resolveCandidateAppliedDepartmentName(Candidate $candidate, ?ApplicantProfile $profile): string
    {
        $value = trim((string) ($candidate->applied_department_name ?? $profile?->applied_department_name ?? ''));

        return $value !== '' ? $value : '-';
    }

    private function resolveCandidateAppliedOutletName(Candidate $candidate, ?ApplicantProfile $profile): string
    {
        $value = trim((string) ($candidate->applied_outlet_name ?? $profile?->applied_outlet_name ?? ''));

        return $value !== '' ? $value : '-';
    }

    private function buildRecruitmentFormGroups()
    {
        $query = AssessmentForm::query()
            ->whereIn('type', array_keys(AssessmentForm::assignableTypes()))
            ->where('is_active', true)
            ->orderBy('name');

        if (AssessmentForm::supportsDepartmentAudienceColumn()) {
            $query->with('department:id,name')->orderBy('department_id');
        }

        return $query->get()
            ->groupBy(fn (AssessmentForm $form) => $form->audienceDepartmentKey())
            ->map(function ($forms, $groupKey) {
                $firstForm = $forms->first();

                return [
                    'key' => $groupKey,
                    'label' => $firstForm?->audienceDepartmentName() ?? 'Umum / Semua Departemen',
                    'department_id' => $firstForm?->department_id,
                    'types' => $forms
                        ->groupBy('type')
                        ->map(function ($groupForms, $type) {
                            return [
                                'type' => $type,
                                'label' => AssessmentForm::labelFor((string) $type),
                                'forms' => $groupForms->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();
    }

    private function applySearchFilter($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function ($subQuery) use ($search) {
            $subQuery->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%");
        });
    }

    private function applyTabFilter($query, string $tab)
    {
        if ($tab === self::TAB_DONE) {
            return $query->where(function ($subQuery) {
                $subQuery
                    ->where('status', Candidate::STATUS_ACCEPTED)
                    ->orWhereHas('assessment', function ($assessmentQuery) {
                        $assessmentQuery->where('status', CandidateAssessment::STATUS_PASSED);
                    });
            });
        }

        if ($tab === self::TAB_REJECTED) {
            return $query->where(function ($subQuery) {
                $subQuery
                    ->where('status', Candidate::STATUS_REJECTED)
                    ->orWhereHas('assessment', function ($assessmentQuery) {
                        $assessmentQuery->where('status', CandidateAssessment::STATUS_REJECTED);
                    });
            });
        }

        if ($tab === self::TAB_BLOCKED) {
            return $query->where(function ($subQuery) {
                $subQuery
                    ->where('status', Candidate::STATUS_BLOCKED)
                    ->orWhereHas('assessment', function ($assessmentQuery) {
                        $assessmentQuery->where('status', CandidateAssessment::STATUS_BLOCKED);
                    });
            });
        }

        $query->whereNotIn('status', self::FINAL_CANDIDATE_STATUSES)
            ->where(function ($assessmentQuery) {
                $assessmentQuery
                    ->whereDoesntHave('assessment')
                    ->orWhereHas('assessment', function ($statusQuery) {
                        $statusQuery->where('status', CandidateAssessment::STATUS_IN_PROCESS);
                    });
            });

        if ($tab === self::TAB_ACTIVE) {
            return $query->whereHas('formAssignments', function ($assignmentQuery) {
                $assignmentQuery->where('status', FormAssignment::STATUS_OPENED);
            });
        }

        if ($tab === self::TAB_TESTED) {
            return $query
                ->whereHas('formAssignments', function ($assignmentQuery) {
                    $assignmentQuery->whereIn('status', [FormAssignment::STATUS_SUBMITTED, FormAssignment::STATUS_EXPIRED]);
                })
                ->whereDoesntHave('formAssignments', function ($assignmentQuery) {
                    $assignmentQuery->where('status', FormAssignment::STATUS_OPENED);
                });
        }

        return $query->whereDoesntHave('formAssignments', function ($assignmentQuery) {
            $assignmentQuery->whereIn('status', [
                FormAssignment::STATUS_OPENED,
                FormAssignment::STATUS_SUBMITTED,
                FormAssignment::STATUS_EXPIRED,
            ]);
        });
    }

    private function applyTestTypeFilter($query, string $tab, string $testType): void
    {
        if ($tab !== self::TAB_TESTED || $testType === 'all') {
            return;
        }

        $legacyKeyword = in_array($testType, [AssessmentForm::TYPE_TIU, AssessmentForm::TYPE_DIFERENSIAL, AssessmentForm::TYPE_FAT], true)
            ? $testType
            : null;

        $query->whereHas('formAssignments', function ($assignmentQuery) use ($testType, $legacyKeyword) {
            $assignmentQuery
                ->whereIn('status', [FormAssignment::STATUS_SUBMITTED, FormAssignment::STATUS_EXPIRED])
                ->whereHas('form', function ($formQuery) use ($testType, $legacyKeyword) {
                    $formQuery->where(function ($nested) use ($testType, $legacyKeyword) {
                        $nested->where('type', $testType);

                        if ($legacyKeyword !== null) {
                            $nested->orWhere(function ($legacyQuery) use ($legacyKeyword) {
                                $legacyQuery->where('type', AssessmentForm::TYPE_CUSTOM)
                                    ->where(function ($searchQuery) use ($legacyKeyword) {
                                        $searchQuery->whereRaw('LOWER(name) like ?', ['%' . $legacyKeyword . '%'])
                                            ->orWhereRaw('LOWER(COALESCE(code, "")) like ?', ['%' . $legacyKeyword . '%']);
                                    });
                            });
                        }
                    });
                });
        });
    }

    private function applyListSorting($query, string $tab, string $sort): void
    {
        if ($tab === self::TAB_TESTED) {
            if ($sort === 'name_asc') {
                $query->orderBy('full_name');
                return;
            }

            if ($sort === 'name_desc') {
                $query->orderByDesc('full_name');
                return;
            }

            if ($sort === 'oldest_test') {
                $query->orderBy('latest_test_activity_at')->orderBy('id');
                return;
            }

            $query->orderByDesc('latest_test_activity_at')->orderByDesc('id');
            return;
        }

        $query->orderByDesc('id');
    }

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, ['latest', 'latest_test', 'oldest_test', 'name_asc', 'name_desc'], true)
            ? $sort
            : 'latest_test';
    }

    private function normalizeTestType(string $testType): string
    {
        $testType = strtolower(trim($testType));
        $allowed = array_merge(['all'], AssessmentForm::allTypes());

        return in_array($testType, $allowed, true)
            ? $testType
            : 'all';
    }

    private function latestTestActivitySubquery()
    {
        return DB::table('form_assignments')
            ->leftJoin('form_attempts', 'form_attempts.form_assignment_id', '=', 'form_assignments.id')
            ->whereColumn('form_assignments.candidate_id', 'candidates.id')
            ->selectRaw('MAX(COALESCE(form_attempts.submitted_at, form_attempts.started_at, form_assignments.closed_at, form_assignments.updated_at, form_assignments.opened_at, form_assignments.created_at))');
    }

}





