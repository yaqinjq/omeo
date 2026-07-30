<?php

namespace App\Services;

use App\Models\ApplicantProfile;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeBankAccountFile;
use App\Models\ProfileChangeRequest;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingProgramEnrollment;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeProfileService
{
    private const PROFILE_FIELDS = ['full_name', 'email_private', 'phone_number'];

    private const PAYROLL_FIELDS = [
        'sim_number',
        'npwp_number',
        'bpjs_kes_number',
        'bpjs_tk_number',
        'passport_number',
        'kk_number',
    ];

    private const PAYROLL_FILE_FIELDS = [
        'sim_file',
        'npwp_file',
        'bpjs_kes_file',
        'bpjs_tk_file',
        'passport_file',
        'kk_file',
    ];

    private const BANK_OPTIONS = [
        ['code' => 'bca', 'name' => 'BCA'],
        ['code' => 'bni', 'name' => 'BNI'],
        ['code' => 'bri', 'name' => 'BRI'],
        ['code' => 'mandiri', 'name' => 'Bank Mandiri'],
        ['code' => 'btn', 'name' => 'BTN'],
        ['code' => 'bsi', 'name' => 'BSI'],
        ['code' => 'cimb', 'name' => 'CIMB Niaga'],
        ['code' => 'danamon', 'name' => 'Danamon'],
        ['code' => 'permata', 'name' => 'PermataBank'],
        ['code' => 'ocbc', 'name' => 'OCBC'],
        ['code' => 'maybank', 'name' => 'Maybank'],
        ['code' => 'panin', 'name' => 'Panin Bank'],
        ['code' => 'seabank', 'name' => 'SeaBank'],
        ['code' => 'jago', 'name' => 'Bank Jago'],
        ['code' => 'blu', 'name' => 'blu by BCA Digital'],
        ['code' => 'lainnya', 'name' => 'Lainnya'],
    ];

    public function __construct(
        private readonly PayrollProfileTargetService $payrollProfileTargetService,
        private readonly ApplicantProfileResolver $applicantProfileResolver
    ) {
    }

    public function getBankOptions(): array
    {
        return self::BANK_OPTIONS;
    }

    /**
     * @return array<string,mixed>
     */
    public function getEmployeeSnapshot(User $user): array
    {
        $relations = [
            'employee.department',
            'employee.position',
            'employee.outlet',
            'applicantProfile',
        ];

        if (Schema::hasTable('employee_salary_histories')) {
            $relations[] = 'employee.salaryHistories.actor';
        }

        if (Schema::hasTable('appraisals')) {
            $relations[] = 'employee.appraisals.period';
            if (Schema::hasTable('appraisal_details') && Schema::hasTable('appraisal_indicators')) {
                $relations[] = 'employee.appraisals.details.indicator';
            }
        }

        if (Schema::hasTable('training_material_progress') && Schema::hasTable('training_materials')) {
            $relations[] = 'employee.trainingMaterialProgress.material';
            $relations[] = 'employee.trainingMaterialProgress.program';
        }

        $user->loadMissing($relations);

        $employee = $user->employee;
        $applicantProfile = $employee
            ? $this->applicantProfileResolver->resolveForEmployee($employee)
            : $user->applicantProfile;
        $payroll = $this->payrollProfileTargetService->getCurrent((int) $user->id, $user->employee_id ? (int) $user->employee_id : null);

        return $this->assembleSnapshot($employee, $user, $applicantProfile, $payroll);
    }

    /**
     * @return array<string,mixed>
     */
    public function getEmployeeSnapshotForEmployee(Employee $employee): array
    {
        $relations = [
            'user',
            'department',
            'position',
            'outlet',
        ];

        if (Schema::hasTable('employee_salary_histories')) {
            $relations[] = 'salaryHistories.actor';
        }

        if (Schema::hasTable('appraisals')) {
            $relations[] = 'appraisals.period';
            if (Schema::hasTable('appraisal_details') && Schema::hasTable('appraisal_indicators')) {
                $relations[] = 'appraisals.details.indicator';
            }
        }

        if (Schema::hasTable('training_material_progress') && Schema::hasTable('training_materials')) {
            $relations[] = 'trainingMaterialProgress.material';
            $relations[] = 'trainingMaterialProgress.program';
        }

        $employee->loadMissing($relations);

        $applicantProfile = $this->applicantProfileResolver->resolveForEmployee($employee);
        $linkedUser = $employee->user ?: $applicantProfile?->user;
        $payroll = $this->payrollProfileTargetService->getCurrent((int) ($linkedUser?->id ?? 0), (int) $employee->id);

        return $this->assembleSnapshot($employee, $linkedUser, $applicantProfile, $payroll);
    }

    /**
     * @return array<string,mixed>
     */
    public function buildEditableProfileData(User $user, ?ApplicantProfile $profile = null, ?array $payroll = null): array
    {
        $profile ??= $user->employee
            ? $this->applicantProfileResolver->resolveForEmployee($user->employee)
            : $user->applicantProfile;
        $payroll ??= $this->payrollProfileTargetService->getCurrent((int) $user->id, $user->employee_id ? (int) $user->employee_id : null);

        return $this->buildEditableProfileDataForContext($user, $user->employee, $profile, $payroll);
    }
public function getBankAccounts(int $employeeId): array
    {
        if ($employeeId <= 0 || !Schema::hasTable('employee_bank_accounts')) {
            return [];
        }

        $accounts = EmployeeBankAccount::query()
            ->with('files')
            ->where('employee_id', $employeeId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return $accounts->map(function (EmployeeBankAccount $account): array {
            return [
                'id' => (int) $account->id,
                'bank_code' => (string) ($account->bank_code ?? ''),
                'bank_name' => (string) ($account->bank_name ?? ''),
                'account_number' => (string) ($account->account_number ?? ''),
                'account_holder_name' => (string) ($account->account_holder_name ?? ''),
                'is_primary' => (bool) $account->is_primary,
                'files' => $account->files->map(fn (EmployeeBankAccountFile $file): array => [
                    'id' => (int) $file->id,
                    'file_path' => (string) $file->file_path,
                    'original_name' => (string) ($file->original_name ?? basename((string) $file->file_path)),
                ])->values()->all(),
            ];
        })->values()->all();
    }


    /**
     * @return array<string,array<int,array<string,string>>>
     */
    /**
     * @return array<string,array<int,array<string,string>>>
     */
    private function normalizeCandidateSections(?ApplicantProfile $profile): array
    {
        $empty = [
            'families' => [],
            'educations' => [],
            'languages' => [],
            'courses' => [],
            'work_experiences' => [],
            'reference_contacts' => [],
            'organizations' => [],
            'medical_histories' => [],
            'social_medias' => [],
        ];

        if (! $profile) {
            return $empty;
        }

        return [
            'families' => $this->mapSectionRows($profile->families ?? [], fn (array $row): array => [
                'relation' => $this->resolveProfileValue([$row['relation'] ?? null, $row['relationship'] ?? null], ''),
                'name' => $this->resolveProfileValue([$row['name'] ?? null, $row['full_name'] ?? null], ''),
                'gender' => $this->resolveProfileValue([$row['gender'] ?? null], ''),
                'dob' => $this->resolveProfileValue([$row['dob'] ?? null, $row['date_of_birth'] ?? null], ''),
                'education' => $this->resolveProfileValue([$row['education'] ?? null], ''),
                'job' => $this->resolveProfileValue([$row['job'] ?? null, $row['occupation'] ?? null], ''),
                'phone' => $this->resolveProfileValue([$row['phone'] ?? null, $row['phone_number'] ?? null], ''),
                'address' => $this->resolveProfileValue([$row['address'] ?? null], ''),
            ]),
            'educations' => $this->mapSectionRows($profile->educations ?? [], fn (array $row): array => [
                'level' => $this->resolveProfileValue([$row['level'] ?? null, $row['education_level'] ?? null, $row['degree'] ?? null], ''),
                'school' => $this->resolveProfileValue([$row['school'] ?? null, $row['school_name'] ?? null, $row['institution'] ?? null], ''),
                'major' => $this->resolveProfileValue([$row['major'] ?? null, $row['field_of_study'] ?? null], ''),
                'year_in' => $this->resolveProfileValue([$row['year_in'] ?? null, $row['start_year'] ?? null], ''),
                'year_out' => $this->resolveProfileValue([$row['year_out'] ?? null, $row['end_year'] ?? null, $row['graduation_year'] ?? null], ''),
                'gpa' => $this->resolveProfileValue([$row['gpa'] ?? null, $row['score'] ?? null], ''),
            ]),
            'languages' => $this->mapSectionRows($profile->languages ?? [], fn (array $row): array => [
                'language' => $this->resolveProfileValue([$row['language'] ?? null, $row['name'] ?? null], ''),
                'speaking' => $this->resolveProfileValue([$row['speaking'] ?? null, $row['proficiency'] ?? null, $row['level'] ?? null], ''),
                'writing' => $this->resolveProfileValue([$row['writing'] ?? null, $row['proficiency'] ?? null, $row['level'] ?? null], ''),
            ]),
            'courses' => $this->mapSectionRows($profile->courses ?? [], fn (array $row): array => [
                'name' => $this->resolveProfileValue([$row['name'] ?? null, $row['course_name'] ?? null], ''),
                'organizer' => $this->resolveProfileValue([$row['organizer'] ?? null, $row['institution'] ?? null], ''),
                'year' => $this->resolveProfileValue([$row['year'] ?? null, $row['completion_year'] ?? null], ''),
                'certificate' => $this->resolveProfileValue([$row['certificate'] ?? null], ''),
            ]),
            'work_experiences' => $this->mapSectionRows($profile->work_experiences ?? [], fn (array $row): array => [
                'company' => $this->resolveProfileValue([$row['company'] ?? null, $row['company_name'] ?? null], ''),
                'position' => $this->resolveProfileValue([$row['position'] ?? null, $row['job_title'] ?? null], ''),
                'date_start' => $this->resolveProfileValue([$row['date_start'] ?? null, $row['start_date'] ?? null], ''),
                'date_end' => $this->resolveProfileValue([$row['date_end'] ?? null, $row['end_date'] ?? null], ''),
                'salary' => $this->resolveProfileValue([$row['salary'] ?? null], ''),
                'reason' => $this->resolveProfileValue([$row['reason'] ?? null, $row['job_description'] ?? null, $row['description'] ?? null], ''),
            ]),
            'reference_contacts' => $this->mapSectionRows($profile->reference_contacts ?? [], fn (array $row): array => [
                'name' => $this->resolveProfileValue([$row['name'] ?? null], ''),
                'relation' => $this->resolveProfileValue([$row['relation'] ?? null, $row['relationship'] ?? null], ''),
                'company' => $this->resolveProfileValue([$row['company'] ?? null], ''),
                'phone' => $this->resolveProfileValue([$row['phone'] ?? null, $row['phone_number'] ?? null], ''),
            ]),
            'organizations' => $this->mapSectionRows($profile->organizations ?? [], fn (array $row): array => [
                'name' => $this->resolveProfileValue([$row['name'] ?? null, $row['organization_name'] ?? null], ''),
                'role' => $this->resolveProfileValue([$row['role'] ?? null, $row['position'] ?? null], ''),
                'year' => $this->resolveProfileValue([$row['year'] ?? null, $row['period'] ?? null], ''),
            ]),
            'medical_histories' => $this->mapSectionRows($profile->medical_histories ?? [], fn (array $row): array => [
                'illness' => $this->resolveProfileValue([$row['illness'] ?? null, $row['condition'] ?? null, $row['name'] ?? null], ''),
                'year' => $this->resolveProfileValue([$row['year'] ?? null], ''),
                'hospitalized' => $this->resolveProfileValue([$row['hospitalized'] ?? null, $row['status'] ?? null], ''),
                'note' => $this->resolveProfileValue([$row['note'] ?? null, $row['notes'] ?? null, $row['description'] ?? null], ''),
            ]),
            'social_medias' => $this->mapSectionRows($profile->social_medias ?? [], fn (array $row): array => [
                'platform' => $this->resolveProfileValue([$row['platform'] ?? null, $row['name'] ?? null], ''),
                'handle' => $this->resolveProfileValue([$row['handle'] ?? null, $row['account'] ?? null, $row['username'] ?? null, $row['url'] ?? null], ''),
            ]),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param callable(array<string,mixed>):array<string,string> $mapper
     * @return array<int,array<string,string>>
     */
    private function mapSectionRows(array $rows, callable $mapper): array
    {
        $normalized = [];

        foreach ($this->normalizeRows($rows) as $row) {
            $mapped = $mapper($row);
            $hasAnyValue = collect($mapped)->contains(static fn ($value) => trim((string) $value) !== '');

            if ($hasAnyValue) {
                $normalized[] = $mapped;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>
     */
    private function assembleSnapshot(?Employee $employee, ?User $linkedUser, ?ApplicantProfile $applicantProfile, array $payroll): array
    {
        $personal = $applicantProfile?->normalizedPersonalJson() ?? [];
        $bankAccounts = $employee ? $this->getBankAccounts((int) $employee->id) : [];
        $appraisals = $employee && $employee->relationLoaded('appraisals')
            ? $employee->appraisals->sortByDesc(fn ($appraisal) => (string) ($appraisal->date_appraised ?? $appraisal->created_at ?? ''))->values()
            : collect();
        $trainingParticipations = $employee && $employee->relationLoaded('trainingMaterialProgress')
            ? $employee->trainingMaterialProgress->sortByDesc(fn ($training) => (string) ($training->completion_date ?? $training->completed_at ?? $training->updated_at ?? ''))->values()
            : collect();
        $trainingParticipations = $this->normalizeTrainingProgressRows($trainingParticipations);
        $salaryHistories = $employee && $employee->relationLoaded('salaryHistories')
            ? $employee->salaryHistories->sortByDesc(fn ($history) => (string) ($history->effective_date ?? $history->created_at ?? ''))->values()
            : collect();

        return [
            'profile' => [
                'full_name' => $this->resolveProfileValue([
                    $employee?->full_name,
                    data_get($personal, 'full_name'),
                    $linkedUser?->name,
                ]),
                'nik' => $this->resolveProfileValue([
                    $employee?->nik,
                    $applicantProfile?->ktp_number,
                ], '-'),
                'employee_number' => (string) ($employee->employee_number ?? '-'),
                'email_login' => $this->resolveProfileValue([$linkedUser?->email], '-'),
                'email_private' => $this->resolveProfileValue([
                    $employee?->email_private,
                    data_get($personal, 'email'),
                    $linkedUser?->email,
                ], '-'),
                'phone_number' => $this->resolveProfileValue([
                    $employee?->phone_number,
                    $applicantProfile?->whatsapp,
                ], '-'),
                'status_employment' => (string) ($employee->status_employment ?? $linkedUser?->role ?? '-'),
                'department' => (string) ($employee?->department?->name ?? '-'),
                'position' => (string) ($employee?->position?->name ?? $employee?->jabatan ?? '-'),
                'outlet' => (string) ($employee?->outlet?->name ?? '-'),
                'join_date' => $employee?->join_date,
                'probation_end_date' => $employee?->probation_end_date,
                'current_salary' => $employee?->current_salary,
            ],
            'payroll' => $payroll,
            'bank_accounts' => $bankAccounts,
            'applicant_profile' => $applicantProfile,
            'linked_user' => $linkedUser,
            'employee' => $employee,
            'appraisals' => $appraisals,
            'training_participations' => $trainingParticipations,
            'training_summary' => $this->buildTrainingSummary($trainingParticipations, $employee),
            'salary_histories' => $salaryHistories,
            'profile_change_requests' => $this->profileChangeRequestsForContext($linkedUser),
            'candidate_sections' => $this->normalizeCandidateSections($applicantProfile),
            'editable_form' => $this->buildEditableProfileDataForContext($linkedUser, $employee, $applicantProfile, $payroll),
            'documents' => $this->buildDocuments($applicantProfile),
            'additional_documents' => $this->buildAdditionalDocuments($applicantProfile),
            'meta' => [
                'uses_applicant_profile' => $applicantProfile !== null,
                'has_bank_accounts_table' => Schema::hasTable('employee_bank_accounts'),
                'has_salary_history_table' => Schema::hasTable('employee_salary_histories'),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildEditableProfileDataForContext(?User $user, ?Employee $employee, ?ApplicantProfile $profile, ?array $payroll = null): array
    {
        $payroll ??= $this->payrollProfileTargetService->getCurrent((int) ($user?->id ?? 0), $employee?->id ? (int) $employee->id : null);
        $personal = $profile?->normalizedPersonalJson() ?? [];

        return [
            'personal' => array_merge([
                'full_name' => $this->resolveProfileValue([
                    $employee?->full_name,
                    data_get($personal, 'full_name'),
                    $user?->name,
                ], ''),
                'ktp_number' => '',
                'place_of_birth' => '',
                'date_of_birth' => '',
                'gender' => '',
                'religion' => '',
                'blood_type' => '',
                'marital_status' => '',
                'marriage_date' => '',
                'email' => $this->resolveProfileValue([
                    $employee?->email_private,
                    data_get($personal, 'email'),
                    $user?->email,
                ], ''),
                'whatsapp' => $this->resolveProfileValue([
                    $employee?->phone_number,
                    data_get($personal, 'whatsapp'),
                ], ''),
                'photo_path' => '',
                'ktp_path' => '',
                'cv_path' => '',
            ], $personal),
            'address' => array_merge([
                'ktp_address' => '',
                'ktp_province' => '',
                'ktp_city' => '',
                'domicile_address' => '',
            ], $profile?->address_json ?? []),
            'families' => $profile?->families ?? [],
            'educations' => $profile?->educations ?? [],
            'languages' => $profile?->languages ?? [],
            'courses' => $profile?->courses ?? [],
            'work_experiences' => $profile?->work_experiences ?? [],
            'reference_contacts' => $profile?->reference_contacts ?? [],
            'organizations' => $profile?->organizations ?? [],
            'medical_histories' => $profile?->medical_histories ?? [],
            'social_medias' => $profile?->social_medias ?? [],
            'payroll' => $payroll,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function buildDocuments(?ApplicantProfile $profile): array
    {
        return [
            'photo' => $this->buildDocumentMeta($profile?->photo_path),
            'ktp' => $this->buildDocumentMeta($profile?->ktp_path),
            'cv' => $this->buildDocumentMeta($profile?->cv_path),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDocumentMeta(?string $path): array
    {
        $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

        return [
            'path' => $path,
            'extension' => $extension !== '' ? strtoupper($extension) : '-',
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
            'exists' => filled($path),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildAdditionalDocuments(?ApplicantProfile $profile): array
    {
        $personal = $profile?->normalizedPersonalJson() ?? [];
        $graduation = is_array($personal['graduation_documents'] ?? null) ? $personal['graduation_documents'] : [];

        return [
            'diploma' => $this->buildDocumentMeta($graduation['diploma_path'] ?? null),
            'transcript' => $this->buildDocumentMeta($graduation['transcript_path'] ?? null),
            'birth_certificate' => $this->buildDocumentMeta($graduation['birth_certificate_path'] ?? null),
            'supporting_files' => collect($graduation['supporting_files'] ?? [])
                ->filter(fn ($path) => filled($path))
                ->map(fn ($path) => $this->buildDocumentMeta($path))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildTrainingSummary(Collection $trainingParticipations, ?Employee $employee = null): array
    {
        return [
            'total' => $trainingParticipations->count(),
            'completed' => $trainingParticipations->where('status', 'completed')->count(),
            'in_progress' => $trainingParticipations->where('status', 'in_progress')->count(),
            'latest' => $trainingParticipations->take(5),
            'programs' => $this->trainingProgramSummaryForEmployee($employee),
            'events' => $this->trainingEventSummaryForEmployee($employee),
        ];
    }

    private function normalizeTrainingProgressRows(Collection $trainingParticipations): Collection
    {
        return $trainingParticipations->map(function ($training) {
            $progress = $this->normalizePercent($training->progress_percent ?? null);
            $isCompleted = (string) ($training->status ?? '') === 'completed'
                || filled($training->completed_at ?? null);

            if ($isCompleted) {
                $training->setAttribute('status', 'completed');
                $progress = max($progress, 100.0);
            }

            $training->setAttribute('progress_percent', $progress);

            return $training;
        })->values();
    }

    private function trainingProgramSummaryForEmployee(?Employee $employee): Collection
    {
        if (! $employee || ! Schema::hasTable('training_program_enrollments')) {
            return collect();
        }

        $query = TrainingProgramEnrollment::query()
            ->with('program:id,name')
            ->where('employee_id', $employee->id)
            ->orderByDesc('completed_at')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('updated_at')
            ->limit(10);

        if (Schema::hasTable('training_material_progress')) {
            $query->with(['progressItems' => fn ($nested) => $nested
                ->select('id', 'training_program_enrollment_id', 'status', 'progress_percent', 'completed_at')]);
        }

        return $query->get()->map(function (TrainingProgramEnrollment $enrollment) {
            $progressItems = $enrollment->relationLoaded('progressItems')
                ? $this->normalizeTrainingProgressRows($enrollment->progressItems)
                : collect();
            $totalMaterials = $progressItems->count();
            $completedMaterials = $progressItems->where('status', 'completed')->count();
            $calculatedPercent = $totalMaterials > 0
                ? round(($completedMaterials / $totalMaterials) * 100, 2)
                : 0.0;
            $storedPercent = $this->normalizePercent($enrollment->progress_percent ?? null);
            $isCompleted = (string) ($enrollment->status ?? '') === 'completed'
                || filled($enrollment->completed_at ?? null)
                || ($totalMaterials > 0 && $completedMaterials === $totalMaterials);

            $enrollment->setAttribute('status', $isCompleted ? 'completed' : (string) ($enrollment->status ?? 'assigned'));
            $enrollment->setAttribute('progress_percent', $isCompleted ? 100.0 : max($storedPercent, $calculatedPercent));
            $enrollment->setAttribute('completed_materials_count', $completedMaterials);
            $enrollment->setAttribute('total_materials_count', $totalMaterials);

            return $enrollment;
        })->values();
    }

    private function trainingEventSummaryForEmployee(?Employee $employee): Collection
    {
        if (! $employee || ! Schema::hasTable('training_event_participants') || ! Schema::hasTable('training_events')) {
            return collect();
        }

        return TrainingEventParticipant::query()
            ->with('event:id,title,starts_at,status')
            ->where('employee_id', $employee->id)
            ->orderByDesc('checked_in_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();
    }

    private function normalizePercent(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return max(0.0, min(100.0, (float) $value));
    }

    private function profileChangeRequestsForContext(?User $linkedUser): Collection
    {
        if (! Schema::hasTable('profile_change_requests') || ! $linkedUser) {
            return collect();
        }

        return ProfileChangeRequest::query()
            ->with(['reviewer:id,name'])
            ->where('user_id', $linkedUser->id)
            ->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)
            ->latest('id')
            ->limit(10)
            ->get();
    }    /**
     * @param array<string,mixed> $profileChanges
     * @param array<string,mixed> $payrollChanges
     * @param array<string,mixed> $payrollAttachments
     * @param array<int,array<string,mixed>> $bankAccounts
     * @param array<string,mixed> $applicantProfileChanges
     */
    public function createChangeRequest(
        User $user,
        array $profileChanges = [],
        array $payrollChanges = [],
        array $payrollAttachments = [],
        array $bankAccounts = [],
        array $applicantProfileChanges = []
    ): ProfileChangeRequest {
        return ProfileChangeRequest::query()->create([
            'user_id' => $user->id,
            'entity_type' => ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE,
            'changes_json' => array_filter([
                'profile' => $this->sanitizeProfileChanges($profileChanges),
                'payroll' => $this->sanitizePayrollChanges($payrollChanges),
                'bank_accounts' => $this->sanitizeBankAccounts($bankAccounts),
                'applicant_profile' => $this->sanitizeApplicantProfileChanges($applicantProfileChanges),
            ], static fn ($section) => $section !== []),
            'attachments_json' => array_filter([
                'payroll' => $this->sanitizePayrollAttachments($payrollAttachments),
            ], static fn ($section) => $section !== []),
            'status' => ProfileChangeRequest::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
    }

    /**
     * @return array{profile:array<string,mixed>,payroll:array<string,mixed>,payroll_attachments:array<string,mixed>,bank_accounts:array<int,array<string,mixed>>,applicant_profile:array<string,mixed>}
     */
    public function parseChangeRequest(ProfileChangeRequest $changeRequest): array
    {
        $changes = $changeRequest->changes_json ?? [];
        $attachments = $changeRequest->attachments_json ?? [];

        $isStructured = is_array($changes) && (
            array_key_exists('profile', $changes)
            || array_key_exists('payroll', $changes)
            || array_key_exists('bank_accounts', $changes)
            || array_key_exists('applicant_profile', $changes)
        );

        if ($isStructured) {
            return [
                'profile' => $this->sanitizeProfileChanges((array) ($changes['profile'] ?? [])),
                'payroll' => $this->sanitizePayrollChanges((array) ($changes['payroll'] ?? [])),
                'payroll_attachments' => $this->sanitizePayrollAttachments((array) ($attachments['payroll'] ?? [])),
                'bank_accounts' => $this->sanitizeBankAccounts((array) ($changes['bank_accounts'] ?? [])),
                'applicant_profile' => $this->sanitizeApplicantProfileChanges((array) ($changes['applicant_profile'] ?? [])),
            ];
        }

        return [
            'profile' => [],
            'payroll' => $this->sanitizePayrollChanges(is_array($changes) ? $changes : []),
            'payroll_attachments' => $this->sanitizePayrollAttachments(is_array($attachments) ? $attachments : []),
            'bank_accounts' => [],
            'applicant_profile' => [],
        ];
    }

    public function applyApprovedChangeRequest(ProfileChangeRequest $changeRequest): bool
    {
        $changeRequest->loadMissing('user');
        $user = $changeRequest->user;
        if (! $user) {
            return false;
        }

        $employeeId = (int) ($user->employee_id ?? 0);
        $payload = $this->parseChangeRequest($changeRequest);

        return (bool) DB::transaction(function () use ($user, $employeeId, $payload) {
            if ($payload['profile'] !== []) {
                $this->applyProfileChanges($user, $payload['profile']);
            }

            if ($payload['applicant_profile'] !== []) {
                $this->applyApplicantProfileChanges($user, $payload['applicant_profile']);
            }

            if ($payload['payroll'] !== [] || $payload['payroll_attachments'] !== []) {
                $applied = $this->payrollProfileTargetService->applyApprovedChanges(
                    (int) $user->id,
                    $employeeId > 0 ? $employeeId : null,
                    $payload['payroll'],
                    $payload['payroll_attachments']
                );

                if (! $applied) {
                    return false;
                }
            }

            if ($payload['bank_accounts'] !== []) {
                if ($employeeId <= 0 || !Schema::hasTable('employee_bank_accounts')) {
                    return false;
                }

                $this->syncBankAccounts($employeeId, $payload['bank_accounts']);
            }

            return true;
        });
    }


    /**
     * @param array<string,mixed> $profileChanges
     * @return array<string,mixed>
     */
    private function sanitizeProfileChanges(array $profileChanges): array
    {
        $clean = [];
        foreach (self::PROFILE_FIELDS as $field) {
            if (array_key_exists($field, $profileChanges)) {
                $clean[$field] = trim((string) $profileChanges[$field]);
            }
        }

        return array_filter($clean, static fn ($value) => $value !== '');
    }

    /**
     * @param array<string,mixed> $applicantProfileChanges
     * @return array<string,mixed>
     */
    private function sanitizeApplicantProfileChanges(array $applicantProfileChanges): array
    {
        $allowed = [
            'personal',
            'address',
            'family',
            'education',
            'language',
            'course',
            'work',
            'organization',
            'reference_contacts',
            'medical',
            'social',
        ];

        $clean = [];
        foreach ($allowed as $section) {
            if (!array_key_exists($section, $applicantProfileChanges)) {
                continue;
            }

            $value = $applicantProfileChanges[$section];
            if (in_array($section, ['personal', 'address'], true)) {
                $clean[$section] = $this->trimNested($value);
                continue;
            }

            $clean[$section] = $this->normalizeRows((array) $value);
        }

        return array_filter($clean, static fn ($value) => $value !== [] && $value !== null);
    }

    /**
     * @param array<string,mixed> $payrollChanges
     * @return array<string,mixed>
     */
    private function sanitizePayrollChanges(array $payrollChanges): array
    {
        $clean = [];
        foreach (self::PAYROLL_FIELDS as $field) {
            if (array_key_exists($field, $payrollChanges)) {
                $clean[$field] = trim((string) $payrollChanges[$field]);
            }
        }

        return $clean;
    }

    /**
     * @param array<string,mixed> $attachments
     * @return array<string,mixed>
     */
    private function sanitizePayrollAttachments(array $attachments): array
    {
        $clean = [];
        foreach (self::PAYROLL_FILE_FIELDS as $field) {
            $value = trim((string) ($attachments[$field] ?? ''));
            if ($value !== '') {
                $clean[$field] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param array<int,array<string,mixed>> $bankAccounts
     * @return array<int,array<string,mixed>>
     */
    private function sanitizeBankAccounts(array $bankAccounts): array
    {
        $clean = [];

        foreach ($bankAccounts as $account) {
            if (!is_array($account)) {
                continue;
            }

            $normalizedFileItems = array_merge(
                (array) ($account['files'] ?? []),
                array_map(static fn ($path): array => ['file_path' => $path], (array) ($account['file_paths'] ?? []))
            );

            $row = [
                'id' => isset($account['id']) && is_numeric($account['id']) ? (int) $account['id'] : null,
                'bank_code' => trim((string) ($account['bank_code'] ?? '')),
                'bank_name' => trim((string) ($account['bank_name'] ?? '')),
                'account_number' => trim((string) ($account['account_number'] ?? '')),
                'account_holder_name' => trim((string) ($account['account_holder_name'] ?? '')),
                'is_primary' => filter_var($account['is_primary'] ?? false, FILTER_VALIDATE_BOOL),
                'file_paths' => array_values(array_unique(array_filter(array_map(
                    static fn ($item) => trim((string) Arr::get($item, 'file_path', $item)),
                    $normalizedFileItems
                )))),
            ];

            $hasAnyValue = $row['bank_name'] !== '' || $row['account_number'] !== '' || $row['account_holder_name'] !== '' || $row['file_paths'] !== [];
            if (! $hasAnyValue) {
                continue;
            }

            $clean[] = $row;
        }

        if ($clean !== [] && !collect($clean)->contains(fn (array $row) => $row['is_primary'])) {
            $clean[0]['is_primary'] = true;
        }

        foreach ($clean as $index => $row) {
            if ($row['bank_name'] === '') {
                $clean[$index]['bank_name'] = $this->resolveBankName($row['bank_code']);
            }
        }

        return array_values($clean);
    }

    private function resolveBankName(?string $code): string
    {
        $code = strtolower(trim((string) $code));
        foreach (self::BANK_OPTIONS as $option) {
            if ($option['code'] === $code) {
                return $option['name'];
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $profileChanges
     */
    private function applyProfileChanges(User $user, array $profileChanges): void
    {
        if ($user->employee) {
            $employeeColumns = Schema::hasTable('employees') ? Schema::getColumnListing('employees') : [];
            $employeePayload = [];
            foreach (self::PROFILE_FIELDS as $field) {
                if (array_key_exists($field, $profileChanges) && in_array($field, $employeeColumns, true)) {
                    $employeePayload[$field] = $profileChanges[$field];
                }
            }

            if ($employeePayload !== []) {
                $user->employee->fill($employeePayload)->save();
            }
        }

        if (!empty($profileChanges['full_name'])) {
            $user->forceFill(['name' => $profileChanges['full_name']])->save();
        }
    }

    /**
     * @param array<string,mixed> $changes
     */
    private function applyApplicantProfileChanges(User $user, array $changes): void
    {
        $profile = ApplicantProfile::firstOrCreate(['user_id' => $user->id], [
            'personal_json' => ['full_name' => $user->name, 'email' => $user->email],
        ]);

        $personal = $this->trimNested($profile->normalizedPersonalJson());
        $address = $this->trimNested($profile->address_json ?? []);

        $personalChanges = (array) ($changes['personal'] ?? []);
        if ($personalChanges !== []) {
            $personal = array_merge($personal, $personalChanges, [
                'email' => $user->email,
            ]);
        }

        if (array_key_exists('reference_contacts', $changes)) {
            $personal['reference_contacts'] = $this->normalizeRows((array) $changes['reference_contacts']);
        }

        if ($personalChanges !== [] || array_key_exists('reference_contacts', $changes)) {
            $profile->personal_json = $profile->syncDocumentAliases($personal);
        }

        $addressChanges = (array) ($changes['address'] ?? []);
        if ($addressChanges !== []) {
            $profile->address_json = array_merge($address, $addressChanges);
        }

        $map = [
            'family' => 'family_json',
            'education' => 'education_json',
            'language' => 'language_json',
            'course' => 'course_json',
            'work' => 'work_json',
            'organization' => 'organization_json',
            'medical' => 'medical_json',
            'social' => 'social_json',
        ];

        foreach ($map as $inputKey => $column) {
            if (array_key_exists($inputKey, $changes)) {
                $profile->{$column} = $this->normalizeRows((array) $changes[$inputKey]);
            }
        }

        $profile->completed_at = empty($profile->getMissingFields()) ? now() : null;
        $profile->save();
    }

    /**
     * @param array<int,array<string,mixed>> $bankAccounts
     */
    private function syncBankAccounts(int $employeeId, array $bankAccounts): void
    {
        $existingAccounts = EmployeeBankAccount::query()
            ->with('files')
            ->where('employee_id', $employeeId)
            ->get()
            ->keyBy('id');

        $keptAccountIds = [];

        foreach ($bankAccounts as $index => $accountData) {
            $accountId = isset($accountData['id']) ? (int) $accountData['id'] : 0;
            $account = $existingAccounts->get($accountId);

            $payload = [
                'employee_id' => $employeeId,
                'bank_code' => $accountData['bank_code'] ?: null,
                'bank_name' => $accountData['bank_name'],
                'account_number' => $accountData['account_number'],
                'account_holder_name' => $accountData['account_holder_name'],
                'is_primary' => (bool) ($accountData['is_primary'] ?? false),
            ];

            if ($account) {
                $account->fill($payload);
                $account->deleted_at = null;
                $account->save();
            } else {
                $account = EmployeeBankAccount::query()->create($payload);
            }

            $keptAccountIds[] = (int) $account->id;
            $this->syncBankAccountFiles($account, (array) ($accountData['file_paths'] ?? []));
        }

        EmployeeBankAccount::query()
            ->where('employee_id', $employeeId)
            ->when($keptAccountIds !== [], fn ($query) => $query->whereNotIn('id', $keptAccountIds))
            ->get()
            ->each(function (EmployeeBankAccount $account): void {
                $account->files()->get()->each(function (EmployeeBankAccountFile $file): void {
                    $file->delete();
                });
                $account->delete();
            });

        if ($keptAccountIds !== []) {
            EmployeeBankAccount::query()
                ->where('employee_id', $employeeId)
                ->whereIn('id', $keptAccountIds)
                ->update(['is_primary' => false]);

            EmployeeBankAccount::query()
                ->where('id', $keptAccountIds[0])
                ->update(['is_primary' => true]);

            foreach ($bankAccounts as $index => $accountData) {
                if (!empty($accountData['is_primary']) && isset($keptAccountIds[$index])) {
                    EmployeeBankAccount::query()
                        ->where('employee_id', $employeeId)
                        ->whereIn('id', $keptAccountIds)
                        ->update(['is_primary' => false]);

                    EmployeeBankAccount::query()
                        ->where('id', $keptAccountIds[$index])
                        ->update(['is_primary' => true]);
                    break;
                }
            }
        }
    }

    /**
     * @param array<int,string> $filePaths
     */
    private function syncBankAccountFiles(EmployeeBankAccount $account, array $filePaths): void
    {
        $existingFiles = $account->files()->get()->keyBy('file_path');
        $normalizedPaths = array_values(array_unique(array_filter(array_map(
            static fn ($path) => trim((string) $path),
            $filePaths
        ))));

        foreach ($normalizedPaths as $path) {
            $file = $existingFiles->get($path);
            if ($file) {
                $file->deleted_at = null;
                $file->save();
                continue;
            }

            EmployeeBankAccountFile::query()->create([
                'employee_bank_account_id' => $account->id,
                'file_path' => $path,
                'original_name' => basename($path),
            ]);
        }

        $account->files()
            ->get()
            ->reject(fn (EmployeeBankAccountFile $file) => in_array($file->file_path, $normalizedPaths, true))
            ->each(fn (EmployeeBankAccountFile $file) => $file->delete());
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>
     */
    private function trimNested(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $clean[$key] = is_string($item) ? trim($item) : $item;
        }

        return $clean;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $trimmed = array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $row);
            $hasAny = false;
            foreach ($trimmed as $value) {
                if ($value !== null && $value !== '' && $value !== []) {
                    $hasAny = true;
                    break;
                }
            }

            if ($hasAny) {
                $normalized[] = $trimmed;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param array<int,mixed> $candidates
     */
    private function resolveProfileValue(array $candidates, string $fallback = '-'): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}










