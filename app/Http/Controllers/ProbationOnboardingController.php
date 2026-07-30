<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProbationOnboardingRequest;
use App\Models\HrNotification;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Services\EmployeeProfileService;
use App\Services\PayrollProfileTargetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProbationOnboardingController extends Controller
{
    public function edit(Request $request, PayrollProfileTargetService $targetService, EmployeeProfileService $employeeProfileService)
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($this->isProbationUser($user), 403);

        $current = $targetService->getCurrent((int) $user->id, $user->employee_id ? (int) $user->employee_id : null);
        $currentBankAccounts = $user->employee_id ? $employeeProfileService->getBankAccounts((int) $user->employee_id) : [];
        $changeRequestsReady = Schema::hasTable('profile_change_requests');

        $pendingRequest = $changeRequestsReady
            ? ProfileChangeRequest::query()
                ->where('user_id', $user->id)
                ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
                ->where('status', ProfileChangeRequest::STATUS_PENDING)
                ->latest('id')
                ->first()
            : null;

        $latestRejected = $changeRequestsReady
            ? ProfileChangeRequest::query()
                ->where('user_id', $user->id)
                ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
                ->where('status', ProfileChangeRequest::STATUS_REJECTED)
                ->latest('id')
                ->first()
            : null;

        $latestApproved = $changeRequestsReady
            ? ProfileChangeRequest::query()
                ->where('user_id', $user->id)
                ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
                ->where('status', ProfileChangeRequest::STATUS_APPROVED)
                ->latest('id')
                ->first()
            : null;

        $isComplete = $targetService->isRequiredComplete($current) && ! empty($currentBankAccounts);
        $isVerified = ! empty($current['payroll_verified_at']);

        return view('probation-onboarding.edit', [
            'current' => $current,
            'currentBankAccounts' => $currentBankAccounts,
            'pendingRequest' => $pendingRequest,
            'latestRejected' => $latestRejected,
            'latestApproved' => $latestApproved,
            'isComplete' => $isComplete,
            'isVerified' => $isVerified,
            'bankOptions' => $employeeProfileService->getBankOptions(),
            'moduleWarning' => $changeRequestsReady ? null : 'Modul approval onboarding belum sepenuhnya siap di environment ini. Form tetap bisa dibuka, tetapi pengajuan baru belum dapat dikirim sampai tabel approval tersedia.',
        ]);
    }

    public function update(
        StoreProbationOnboardingRequest $request,
        PayrollProfileTargetService $targetService,
        EmployeeProfileService $employeeProfileService
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($this->isProbationUser($user), 403);

        if (! Schema::hasTable('profile_change_requests')) {
            return back()->withInput()->with('error', 'Modul approval onboarding belum siap di environment ini. Silakan hubungi HRD atau admin sistem.');
        }

        $pendingExists = ProfileChangeRequest::query()
            ->where('user_id', $user->id)
            ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
            ->where('status', ProfileChangeRequest::STATUS_PENDING)
            ->exists();

        if ($pendingExists) {
            return back()->with('error', 'Pengajuan sebelumnya masih menunggu verifikasi HRD.');
        }

        $current = $targetService->getCurrent((int) $user->id, $user->employee_id ? (int) $user->employee_id : null);
        $currentBankAccounts = $user->employee_id ? $employeeProfileService->getBankAccounts((int) $user->employee_id) : [];
        $existingBankAccountMap = collect($currentBankAccounts)->keyBy('id');

        $changes = [
            'sim_number' => trim((string) $request->string('sim_number')),
            'npwp_number' => trim((string) $request->string('npwp_number')),
            'bpjs_kes_number' => trim((string) $request->string('bpjs_kes_number')),
            'bpjs_tk_number' => trim((string) $request->string('bpjs_tk_number')),
            'passport_number' => trim((string) $request->string('passport_number')),
            'kk_number' => trim((string) $request->string('kk_number')),
        ];

        $attachments = [
            'sim_file' => $this->storeOrKeepFile($request->file('sim_file'), 'sim', $current['sim_file'] ?? null),
            'npwp_file' => $this->storeOrKeepFile($request->file('npwp_file'), 'npwp', $current['npwp_file'] ?? null),
            'bpjs_kes_file' => $this->storeOrKeepFile($request->file('bpjs_kes_file'), 'bpjs-kes', $current['bpjs_kes_file'] ?? null),
            'bpjs_tk_file' => $this->storeOrKeepFile($request->file('bpjs_tk_file'), 'bpjs-tk', $current['bpjs_tk_file'] ?? null),
            'passport_file' => $this->storeOrKeepFile($request->file('passport_file'), 'passport', $current['passport_file'] ?? null),
            'kk_file' => $this->storeOrKeepFile($request->file('kk_file'), 'kk', $current['kk_file'] ?? null),
        ];

        $bankAccounts = [];
        foreach ((array) $request->input('bank_accounts', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
            $existingFiles = array_values(array_filter(array_map(
                static fn ($path) => trim((string) $path),
                (array) ($row['existing_files'] ?? [])
            )));

            if ($id && $existingFiles === []) {
                $existingFiles = collect((array) data_get($existingBankAccountMap->get($id), 'files', []))
                    ->map(fn (array $file): string => (string) ($file['file_path'] ?? ''))
                    ->filter()
                    ->values()
                    ->all();
            }

            $newFiles = $this->storeBankFiles($request->file("bank_accounts.{$index}.files", []));
            $allFiles = array_values(array_unique(array_filter(array_merge($existingFiles, $newFiles))));

            $hasAny = trim((string) ($row['bank_code'] ?? '')) !== ''
                || trim((string) ($row['account_number'] ?? '')) !== ''
                || trim((string) ($row['account_holder_name'] ?? '')) !== ''
                || $allFiles !== [];

            if (! $hasAny) {
                continue;
            }

            $bankAccounts[] = [
                'id' => $id,
                'bank_code' => trim((string) ($row['bank_code'] ?? '')),
                'bank_name' => trim((string) ($row['bank_name'] ?? '')),
                'account_number' => trim((string) ($row['account_number'] ?? '')),
                'account_holder_name' => trim((string) ($row['account_holder_name'] ?? '')),
                'is_primary' => filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOL),
                'files' => array_map(static fn (string $path): array => ['file_path' => $path], $allFiles),
            ];
        }

        $changeRequest = $employeeProfileService->createChangeRequest(
            $user,
            [],
            $changes,
            $attachments,
            $bankAccounts
        );

        $this->notifyHrdForPendingVerification($user, $changeRequest->id);

        return redirect()->route('probation-onboarding.edit')
            ->with('success', 'Data payroll dan rekening berhasil diajukan. Status saat ini: menunggu verifikasi HRD.');
    }

    private function notifyHrdForPendingVerification(User $user, int $changeRequestId): void
    {
        if (! Schema::hasTable('hr_notifications')) {
            return;
        }

        $hrUserIds = User::query()
            ->whereIn('role', ['admin', 'hrd'])
            ->pluck('id');

        foreach ($hrUserIds as $hrUserId) {
            HrNotification::query()->create([
                'user_id' => (int) $hrUserId,
                'type' => 'profile_change_request',
                'title' => 'Verifikasi Data Karyawan Menunggu',
                'body' => 'Ada perubahan data payroll atau rekening dari karyawan probation yang perlu diverifikasi.',
                'due_date' => now()->toDateString(),
                'is_read' => false,
                'unique_key' => 'profile-change-pending-' . $changeRequestId . '-' . $hrUserId . '-' . now()->timestamp,
                'meta' => [
                    'change_request_id' => $changeRequestId,
                    'route' => route('hrd.probation-verifications.show', $changeRequestId),
                    'submitter_user_id' => $user->id,
                ],
            ]);
        }
    }

    private function storeOrKeepFile(?UploadedFile $file, string $folder, ?string $existingPath): ?string
    {
        if ($file === null) {
            return $existingPath;
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        $filename = Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');

        return (string) $file->storeAs('onboarding-docs/' . $folder, $filename, 'public');
    }

    /**
     * @param array<int,UploadedFile>|UploadedFile|null $files
     * @return array<int,string>
     */
    private function storeBankFiles(array|UploadedFile|null $files): array
    {
        $items = $files instanceof UploadedFile ? [$files] : (array) $files;
        $stored = [];

        foreach ($items as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            $filename = Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');
            $stored[] = (string) $file->storeAs('onboarding-docs/bank-accounts', $filename, 'public');
        }

        return $stored;
    }

    private function isProbationUser(User $user): bool
    {
        if (($user->role ?? null) === 'probation') {
            return true;
        }

        if (Schema::hasColumn('users', 'employee_status') && ($user->employee_status ?? null) === 'probation') {
            return true;
        }

        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'status_employment')) {
            return false;
        }

        if ((int) ($user->employee_id ?? 0) <= 0) {
            return false;
        }

        return DB::table('employees')
            ->where('id', (int) $user->employee_id)
            ->where('status_employment', 'probation')
            ->exists();
    }
}
