<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Http\Requests\HRD\RejectEmployeeProfileChangeRequest;
use App\Models\ProfileChangeRequest;
use App\Services\EmployeeProfileService;
use App\Services\Notifications\UnifiedNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ProbationDataVerificationController extends Controller
{
    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ProfileChangeRequest::STATUS_PENDING));
        if (! in_array($status, [ProfileChangeRequest::STATUS_PENDING, ProfileChangeRequest::STATUS_APPROVED, ProfileChangeRequest::STATUS_REJECTED], true)) {
            $status = ProfileChangeRequest::STATUS_PENDING;
        }

        $warning = null;
        if (! Schema::hasTable('profile_change_requests')) {
            $warning = 'Tabel pengajuan perubahan data belum tersedia di environment ini. Halaman tetap dibuka dengan mode aman.';
            $items = $this->emptyPaginator($request, 15);
        } else {
            $items = ProfileChangeRequest::query()
                ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
                ->with(['user:id,name,email,employee_id'])
                ->where('status', $status)
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();
        }

        return view('hrd.probation_verifications.index', [
            'items' => $items,
            'status' => $status,
            'moduleWarning' => $warning,
        ]);
    }

    public function show($changeRequest, EmployeeProfileService $employeeProfileService)
    {
        $changeRequest = $this->resolveChangeRequest($changeRequest);
        if (! $changeRequest) {
            return redirect()->route('hrd.probation-verifications.index')
                ->with('error', 'Data pengajuan verifikasi tidak tersedia atau modulnya belum siap di environment ini.');
        }

        abort_if($changeRequest->entity_type !== ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE, 404);

        $changeRequest->loadMissing(['user:id,name,email,employee_id', 'reviewer:id,name']);
        abort_if(! $changeRequest->user, 404);

        $snapshot = $employeeProfileService->getEmployeeSnapshot($changeRequest->user);
        $requestPayload = $employeeProfileService->parseChangeRequest($changeRequest);

        return view('hrd.probation_verifications.show', [
            'changeRequest' => $changeRequest,
            'oldProfile' => $snapshot['profile'],
            'oldPayroll' => $snapshot['payroll'],
            'oldBankAccounts' => $snapshot['bank_accounts'],
            'oldApplicantProfile' => $snapshot['editable_form'],
            'requestProfile' => $requestPayload['profile'],
            'requestPayroll' => $requestPayload['payroll'],
            'requestPayrollAttachments' => $requestPayload['payroll_attachments'],
            'requestBankAccounts' => $requestPayload['bank_accounts'],
            'requestApplicantProfile' => $requestPayload['applicant_profile'],
        ]);
    }

    public function approve(Request $request, $changeRequest, EmployeeProfileService $employeeProfileService, UnifiedNotificationService $notificationService): RedirectResponse
    {
        $changeRequest = $this->resolveChangeRequest($changeRequest);
        if (! $changeRequest) {
            return redirect()->route('hrd.probation-verifications.index')
                ->with('error', 'Data pengajuan verifikasi tidak tersedia atau modulnya belum siap di environment ini.');
        }

        abort_if($changeRequest->entity_type !== ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE, 404);

        if ($changeRequest->status !== ProfileChangeRequest::STATUS_PENDING) {
            return back()->with('error', 'Request ini tidak lagi berstatus pending.');
        }

        $targetApplied = $employeeProfileService->applyApprovedChangeRequest($changeRequest);

        if (! $targetApplied) {
            return back()->with('error', 'Target data permanen karyawan belum tersedia untuk diupdate.');
        }

        $changeRequest->update([
            'status' => ProfileChangeRequest::STATUS_APPROVED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'review_note' => 'Disetujui HRD.',
        ]);

        $notificationService->notifyEmployeeProfileChangeReviewed(
            $changeRequest->user,
            'Perubahan Data Disetujui',
            'Perubahan data profil atau payroll Anda sudah disetujui HRD dan diterapkan ke data utama.',
            $changeRequest->id,
            'approved'
        );

        return redirect()->route('hrd.probation-verifications.show', $changeRequest->id)
            ->with('success', 'Perubahan data disetujui dan berhasil diterapkan ke data permanen.');
    }

    public function reject(RejectEmployeeProfileChangeRequest $request, $changeRequest, UnifiedNotificationService $notificationService): RedirectResponse
    {
        $changeRequest = $this->resolveChangeRequest($changeRequest);
        if (! $changeRequest) {
            return redirect()->route('hrd.probation-verifications.index')
                ->with('error', 'Data pengajuan verifikasi tidak tersedia atau modulnya belum siap di environment ini.');
        }

        abort_if($changeRequest->entity_type !== ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE, 404);

        if ($changeRequest->status !== ProfileChangeRequest::STATUS_PENDING) {
            return back()->with('error', 'Request ini tidak lagi berstatus pending.');
        }

        $note = trim((string) $request->string('review_note'));

        $changeRequest->update([
            'status' => ProfileChangeRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $notificationService->notifyEmployeeProfileChangeReviewed(
            $changeRequest->user,
            'Perubahan Data Ditolak',
            'Perubahan data profil atau payroll ditolak HRD. Alasan: ' . $note,
            $changeRequest->id,
            'rejected'
        );

        return redirect()->route('hrd.probation-verifications.show', $changeRequest->id)
            ->with('success', 'Request perubahan data ditolak. Notifikasi ke karyawan sudah dikirim.');
    }

    private function resolveChangeRequest(mixed $changeRequest): ?ProfileChangeRequest
    {
        if (! Schema::hasTable('profile_change_requests')) {
            return null;
        }

        $id = is_object($changeRequest) ? ($changeRequest->id ?? null) : $changeRequest;
        if (! is_numeric($id)) {
            return null;
        }

        return ProfileChangeRequest::query()->find((int) $id);
    }

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $request->integer('page', 1), [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
