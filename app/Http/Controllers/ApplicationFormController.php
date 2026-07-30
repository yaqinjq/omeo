<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationFormUpdateRequest;
use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\Position;
use App\Services\ApplicationFormTemporaryUploadService;
use App\Services\CandidateBlacklistService;
use App\Support\ApplicationFormUploadLimit;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\Exception\LogicException;
use Throwable;

class ApplicationFormController extends Controller
{
    public function edit(Request $request, ApplicationFormTemporaryUploadService $temporaryUploadService)
    {
        $user = $request->user();
        $profile = ApplicantProfile::withTrashed()->firstOrCreate(
            ['user_id' => $user->id],
            ['personal_json' => ['full_name' => $user->name, 'email' => $user->email]]
        );

        if ($profile->wasRecentlyCreated === false) {
            $normalizedPersonal = $profile->syncDocumentAliases($profile->normalizedPersonalJson());
            if ($normalizedPersonal !== ($profile->personal_json ?? [])) {
                $profile->personal_json = $normalizedPersonal;
                $profile->save();
            }
        }

        $candidate = $this->resolveCandidateForUser($user, $profile);
        $completion = $profile->getCompletionProgress();
        $positions = $this->loadPositions();
        $departments = $this->loadDepartments();
        $outlets = $this->loadOutlets();
        $hasAdministrativeDocumentStage = $this->hasAdministrativeDocumentStage($candidate);
        $temporaryUploadState = $this->resolveTemporaryUploadState($request, $temporaryUploadService, (int) $user->id);
        $heicConversionAvailable = $temporaryUploadService->canConvertHeic();

        return view('application-form.edit', compact(
            'profile',
            'completion',
            'positions',
            'departments',
            'outlets',
            'candidate',
            'hasAdministrativeDocumentStage',
            'temporaryUploadState',
            'heicConversionAvailable'
        ));
    }

    public function sessionPing(Request $request): JsonResponse
    {
        $request->session()->put('application_form_last_ping_at', now()->toIso8601String());

        return response()->json([
            'ok' => true,
            'authenticated' => $request->user() !== null,
            'csrf_token' => csrf_token(),
            'server_time' => now()->toIso8601String(),
            'upload_limits' => ApplicationFormUploadLimit::describe(),
        ]);
    }

    public function uploadTemporaryDocument(Request $request, ApplicationFormTemporaryUploadService $temporaryUploadService): JsonResponse
    {
        $user = $request->user();
        $field = (string) $request->input('field', '');
        $requestId = (string) Str::uuid();
        $profile = ApplicantProfile::withTrashed()->firstOrCreate(['user_id' => $user->id]);
        $candidate = $this->resolveCandidateForUser($user, $profile);
        $logContext = $this->buildLogContext($request, $requestId, $profile, $candidate) + [
            'upload_mode' => 'temporary_document',
            'field' => $field,
            'previous_token' => (string) $request->input('previous_token', ''),
        ];
        $fieldDefinition = in_array($field, ApplicationFormTemporaryUploadService::supportedFields(), true)
            ? ApplicationFormTemporaryUploadService::fieldDefinition($field)
            : null;

        Log::info('Application form temporary upload started', $logContext + [
            'http_status' => null,
            'field_definition' => $fieldDefinition ? $this->publicUploadDefinition($fieldDefinition) : null,
        ]);

        if ($fieldDefinition === null) {
            $message = 'Field upload tidak dikenali.';
            Log::warning('Application form temporary upload rejected', $logContext + [
                'reason' => 'invalid_field',
                'http_status' => 422,
            ]);

            return $this->temporaryUploadJson(false, $message, $requestId, 'invalid_field', 422, [
                'field' => [$message],
            ]);
        }

        $uploadedFile = $request->file('document');
        if (! $uploadedFile instanceof UploadedFile) {
            $reason = $temporaryUploadService->likelyOversizeRequest($request->server('CONTENT_LENGTH')) ? 'server_limit' : 'missing_file';
            $message = $reason === 'server_limit'
                ? 'Ukuran request melebihi batas upload server. Cek upload_max_filesize, post_max_size, dan client_max_body_size di server production.'
                : 'File belum terbaca oleh server. Silakan pilih ulang file tersebut.';

            Log::warning('Application form temporary upload rejected before file handling', $logContext + [
                'reason' => $reason,
                'http_status' => 422,
                'upload_limits' => ApplicationFormUploadLimit::describe(),
                'field_definition' => $this->publicUploadDefinition($fieldDefinition),
            ]);

            return $this->temporaryUploadJson(false, $message, $requestId, $reason, 422, [
                'document' => [$message],
            ]);
        }

        try {
            $result = $temporaryUploadService->storeTemporaryUpload($uploadedFile, $field, (int) $user->id, $logContext);
            $temporaryUploadService->discardTemporaryUpload((string) $request->input('previous_token', ''), $field, (int) $user->id, $logContext);
            $message = ucfirst((string) ($result['label'] ?? 'Dokumen')) . ' berhasil diunggah.';

            Log::info('Application form temporary upload response ready', $logContext + [
                'reason' => 'ok',
                'http_status' => 200,
                'token' => $result['token'],
                'stored_path' => $result['stored_path'] ?? null,
                'field_definition' => $this->publicUploadDefinition($fieldDefinition),
            ]);

            return response()->json([
                'ok' => true,
                'message' => ucfirst((string) ($result['label'] ?? 'Dokumen')) . ' berhasil diunggah.',
                'request_id' => $requestId,
                'reason' => 'ok',
                'errors' => [],
                'token' => $result['token'],
                'preview_url' => $result['preview_url'],
                'filename' => $result['normalized_name'],
                'upload' => [
                    'token' => $result['token'],
                    'field' => $result['field'],
                    'preview_url' => $result['preview_url'],
                    'filename' => $result['normalized_name'],
                    'original_name' => $result['original_name'],
                    'mime' => $result['mime'],
                    'size_bytes' => $result['size_bytes'],
                    'source' => $result['source'],
                ],
                'diagnostics' => [
                    'effective_limit' => ApplicationFormUploadLimit::humanReadableEffectiveLimit(),
                    'heic_conversion_available' => $temporaryUploadService->canConvertHeic(),
                ],
            ]);
        } catch (RuntimeException $exception) {
            Log::warning('Application form temporary upload failed', $logContext + [
                'reason' => 'runtime_upload_failure',
                'message' => $exception->getMessage(),
                'http_status' => 422,
                'upload_limits' => ApplicationFormUploadLimit::describe(),
                'field_definition' => $this->publicUploadDefinition($fieldDefinition),
            ]);

            return $this->temporaryUploadJson(false, $exception->getMessage(), $requestId, 'upload_failed', 422, [
                'document' => [$exception->getMessage()],
            ]);
        } catch (Throwable $exception) {
            Log::error('Application form temporary upload crashed', $logContext + [
                'reason' => 'unexpected_failure',
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
                'http_status' => 500,
                'upload_limits' => ApplicationFormUploadLimit::describe(),
                'field_definition' => $this->publicUploadDefinition($fieldDefinition),
            ]);

            $message = 'Server gagal memproses upload dokumen. Silakan coba lagi atau hubungi HRD.';

            return $this->temporaryUploadJson(false, $message, $requestId, 'server_error', 500, [
                'document' => [$message],
            ]);
        }
    }

    public function update(ApplicationFormUpdateRequest $request, CandidateBlacklistService $blacklistService, ApplicationFormTemporaryUploadService $temporaryUploadService): RedirectResponse
    {
        $user = $request->user();
        $profile = ApplicantProfile::withTrashed()->firstOrCreate(['user_id' => $user->id]);
        $candidate = $this->resolveCandidateForUser($user, $profile);
        $requestId = (string) ($request->attributes->get('application_form_request_id') ?: Str::uuid());
        $logContext = $this->buildLogContext($request, $requestId, $profile, $candidate);
        $isFinalSubmit = $request->boolean('final_submit');

        Log::info('Application form submit passed validation', $logContext + [
            'upload_limits' => ApplicationFormUploadLimit::describe(),
        ]);

        $validated = $request->validated();
        $submittedFields = array_flip((array) $request->attributes->get('application_form_submitted_fields', []));

        try {
            $personal = $profile->normalizedPersonalJson();
            $this->preserveExistingDocumentPath($personal, 'skck_latest_path', $request->input('skck_existing'), 'applicants/skck');
            $address = is_array($profile->address_json) ? $profile->address_json : [];
            $medical = is_array($profile->medical_json) ? $profile->medical_json : [];
            $positions = $this->loadPositions()->keyBy('id');
            $departments = $this->loadDepartments()->keyBy('id');
            $outlets = $this->loadOutlets()->keyBy('id');

            foreach ([
                'photo_ktp_file' => 'photo_path',
                'scan_ktp_file' => 'ktp_path',
                'cv_file' => 'cv_path',
            ] as $fieldName => $targetKey) {
                $temporaryToken = trim((string) ($validated[$fieldName . '_token'] ?? ''));
                if ($temporaryToken !== '') {
                    if ($temporaryUploadService->findTemporaryUpload($temporaryToken, $fieldName, (int) $user->id) !== null) {
                        $personal[$targetKey] = $temporaryUploadService->promoteTemporaryUpload($temporaryToken, $fieldName, (int) $user->id, $logContext);
                    } else {
                        Log::warning('Application form submit skipped unavailable temporary upload token', $logContext + [
                            'field' => $fieldName,
                            'token_hash' => $this->safeTokenHash($temporaryToken),
                            'has_file_fallback' => $request->hasFile($fieldName),
                        ]);
                    }
                }
            }

            if ($request->hasFile('photo_ktp_file')) {
                $personal['photo_path'] = $this->storeUploadedFileSafely($request->file('photo_ktp_file'), 'photo_ktp_file', 'applicants/photos', $logContext);
            }
            if ($request->hasFile('scan_ktp_file')) {
                $personal['ktp_path'] = $this->storeUploadedFileSafely($request->file('scan_ktp_file'), 'scan_ktp_file', 'applicants/ktp', $logContext);
            }
            if ($request->hasFile('cv_file')) {
                $personal['cv_path'] = $this->storeUploadedFileSafely($request->file('cv_file'), 'cv_file', 'applicants/cv', $logContext);
            }
            if (trim((string) ($validated['signature_data'] ?? '')) !== '') {
                $personal['signature_path'] = $this->storeSignatureData($validated['signature_data'], $logContext);
            }
            if ($request->hasFile('skck_file')) {
                $personal['skck_latest_path'] = $this->storeUploadedFileSafely($request->file('skck_file'), 'skck_file', 'applicants/skck', $logContext);
            }

            $graduationDocuments = [
                'diploma_path' => data_get($personal, 'graduation_documents.diploma_path'),
                'transcript_path' => data_get($personal, 'graduation_documents.transcript_path'),
                'birth_certificate_path' => data_get($personal, 'graduation_documents.birth_certificate_path'),
                'supporting_files' => array_values((array) data_get($personal, 'graduation_documents.supporting_files', [])),
            ];
            $this->preserveExistingDocumentPath($graduationDocuments, 'diploma_path', $request->input('ijazah_existing'), 'applicants/graduation');
            $this->preserveExistingDocumentPath($graduationDocuments, 'transcript_path', $request->input('transkrip_existing'), 'applicants/graduation');
            $this->preserveExistingDocumentPath($graduationDocuments, 'birth_certificate_path', $request->input('akta_lahir_existing'), 'applicants/graduation');

            if ($request->hasFile('graduation_diploma_file')) {
                $graduationDocuments['diploma_path'] = $this->storeUploadedFileSafely($request->file('graduation_diploma_file'), 'graduation_diploma_file', 'applicants/graduation', $logContext);
            }
            if ($request->hasFile('graduation_transcript_file')) {
                $graduationDocuments['transcript_path'] = $this->storeUploadedFileSafely($request->file('graduation_transcript_file'), 'graduation_transcript_file', 'applicants/graduation', $logContext);
            }
            if ($request->hasFile('graduation_birth_certificate_file')) {
                $graduationDocuments['birth_certificate_path'] = $this->storeUploadedFileSafely($request->file('graduation_birth_certificate_file'), 'graduation_birth_certificate_file', 'applicants/graduation', $logContext);
            }
            if ($request->hasFile('supporting_files')) {
                $graduationDocuments['supporting_files'] = [];
                foreach ((array) $request->file('supporting_files', []) as $index => $file) {
                    if ($file instanceof UploadedFile) {
                        $graduationDocuments['supporting_files'][] = $this->storeUploadedFileSafely($file, 'supporting_files.' . $index, 'applicants/supporting', $logContext);
                    }
                }
            }

            $appliedPositionId = $this->normalizeNullableInteger($validated['applied_position_id'] ?? null);
            $appliedDepartmentId = $this->normalizeNullableInteger($validated['applied_department_id'] ?? null);
            $appliedOutletId = $this->normalizeNullableInteger($validated['applied_outlet_id'] ?? null);

            $appliedPositionName = trim((string) ($validated['applied_position_name'] ?? ''));
            if ($appliedPositionName === '' && $appliedPositionId) {
                $appliedPositionName = (string) ($positions->get($appliedPositionId)?->name ?? '');
            }
            $appliedDepartmentName = $appliedDepartmentId ? (string) ($departments->get($appliedDepartmentId)?->name ?? '') : '';
            $appliedOutletName = $appliedOutletId ? (string) ($outlets->get($appliedOutletId)?->name ?? '') : '';

            foreach ([
                'full_name', 'ktp_number', 'place_of_birth', 'date_of_birth', 'time_of_birth', 'gender', 'religion',
                'blood_type', 'marital_status', 'marriage_date', 'whatsapp', 'phone_number', 'salary_expectation',
                'preferred_job_scope', 'preferred_job_scope_other', 'preferred_work_environment',
                'preferred_work_environment_other', 'willing_out_of_town', 'willing_outside_java', 'willing_shift',
                'willing_overtime', 'is_smoker', 'has_computer_skill', 'wears_glasses', 'glasses_right_eye',
                'glasses_left_eye', 'join_reason', 'company_relation_note', 'career_goal', 'additional_information',
                'available_start_date', 'honesty_statement',
            ] as $key) {
                $this->mergeValidatedScalar($personal, $validated, $key, $isFinalSubmit);
            }

            if ($isFinalSubmit || array_key_exists('reference_contacts', $submittedFields)) {
                $personal['reference_contacts'] = $this->cleanRows($validated['reference_contacts']);
            }
            if ($isFinalSubmit || array_key_exists('emergency_contacts', $submittedFields)) {
                $personal['emergency_contacts'] = $this->cleanRows($validated['emergency_contacts']);
            }

            $personal['email'] = $user->email;
            $this->mergeResolvedValue($personal, 'applied_position_id', $appliedPositionId, $isFinalSubmit || array_key_exists('applied_position_id', $validated));
            $this->mergeResolvedValue($personal, 'applied_department_id', $appliedDepartmentId, $isFinalSubmit || array_key_exists('applied_department_id', $validated));
            $this->mergeResolvedValue($personal, 'applied_outlet_id', $appliedOutletId, $isFinalSubmit || array_key_exists('applied_outlet_id', $validated));
            $this->mergeResolvedValue($personal, 'applied_position_name', $appliedPositionName !== '' ? $appliedPositionName : null, $isFinalSubmit || $appliedPositionName !== '');
            $this->mergeResolvedValue($personal, 'applied_position', $appliedPositionName !== '' ? $appliedPositionName : null, $isFinalSubmit || $appliedPositionName !== '');
            $this->mergeResolvedValue($personal, 'applied_department_name', $appliedDepartmentName !== '' ? $appliedDepartmentName : null, $isFinalSubmit || $appliedDepartmentName !== '');
            $this->mergeResolvedValue($personal, 'preferred_department', $appliedDepartmentName !== '' ? $appliedDepartmentName : null, $isFinalSubmit || $appliedDepartmentName !== '');
            $this->mergeResolvedValue($personal, 'applied_outlet_name', $appliedOutletName !== '' ? $appliedOutletName : null, $isFinalSubmit || $appliedOutletName !== '');
            $this->mergeResolvedValue($personal, 'preferred_outlet', $appliedOutletName !== '' ? $appliedOutletName : null, $isFinalSubmit || $appliedOutletName !== '');
            $personal['graduation_documents'] = $graduationDocuments;
            if ($isFinalSubmit) {
                $personal['terms_of_use_acknowledged_at'] = now()->toIso8601String();
            }

            $profile->personal_json = $profile->syncDocumentAliases($personal);
            foreach ([
                'ktp_address', 'ktp_rt', 'ktp_rw', 'ktp_kelurahan', 'ktp_kecamatan', 'ktp_city', 'ktp_province',
                'domicile_address', 'domicile_rt', 'domicile_rw', 'domicile_kelurahan', 'domicile_kecamatan',
                'domicile_city', 'domicile_province',
            ] as $key) {
                $this->mergeValidatedScalar($address, $validated, $key, $isFinalSubmit);
            }
            $profile->address_json = $address;

            foreach ([
                'families' => 'family_json',
                'educations' => 'education_json',
                'languages' => 'language_json',
                'courses' => 'course_json',
                'work_experiences' => 'work_json',
                'organizations' => 'organization_json',
                'social_medias' => 'social_json',
            ] as $inputKey => $profileKey) {
                if ($isFinalSubmit || array_key_exists($inputKey, $submittedFields)) {
                    $profile->{$profileKey} = $this->cleanRows($validated[$inputKey]);
                }
            }

            if ($isFinalSubmit || array_key_exists('medical_histories', $submittedFields)) {
                $medical['histories'] = $this->cleanRows($validated['medical_histories']);
            }
            foreach ([
                'weight_kg', 'height_cm', 'had_accident', 'accident_year', 'accident_type', 'accident_effect',
                'police_record', 'police_record_case', 'police_record_year', 'police_record_location',
                'psychology_test', 'psychology_test_year', 'psychology_test_location', 'psychology_test_purpose',
            ] as $key) {
                $this->mergeValidatedScalar($medical, $validated, $key, $isFinalSubmit);
            }
            $profile->medical_json = $medical;

            if ($isFinalSubmit) {
                $matches = $blacklistService->findMatches(
                    (string) data_get($personal, 'ktp_number', ''),
                    (string) $user->email,
                    (string) data_get($personal, 'whatsapp', '')
                );

                if ($matches->isNotEmpty()) {
                    $types = $matches->pluck('identifier_type')->unique()->implode(', ');

                    Log::warning('Application form final submit blocked by blacklist', $logContext + [
                        'blacklist_identifier_types' => $types,
                    ]);

                    return back()
                        ->withInput($request->except($this->fileInputNames()))
                        ->withErrors(['ktp_number' => 'Data Anda masuk daftar blacklist (' . $types . '). Silakan hubungi HRD.'])
                        ->with('first_error_step', 1)
                        ->with('error', 'Pengajuan tidak dapat diproses karena identitas ter-blacklist.');
                }
            }

            if ($candidate) {
                $candidateUpdates = ['email' => $user->email];
                $this->mergeCandidateValue($candidateUpdates, 'full_name', $validated['full_name'] ?? null, $isFinalSubmit || array_key_exists('full_name', $validated));
                $this->mergeCandidateValue($candidateUpdates, 'phone', $validated['phone_number'] ?? null, $isFinalSubmit || array_key_exists('phone_number', $validated));
                $this->mergeCandidateValue($candidateUpdates, 'nik', $validated['ktp_number'] ?? null, $isFinalSubmit || array_key_exists('ktp_number', $validated));
                $this->mergeCandidateValue($candidateUpdates, 'applied_position_id', $appliedPositionId, $isFinalSubmit || array_key_exists('applied_position_id', $validated));
                $this->mergeCandidateValue($candidateUpdates, 'applied_position_name', $appliedPositionName !== '' ? $appliedPositionName : null, $isFinalSubmit || $appliedPositionName !== '');
                $this->mergeCandidateValue($candidateUpdates, 'applied_department_id', $appliedDepartmentId, $isFinalSubmit || array_key_exists('applied_department_id', $validated));
                $this->mergeCandidateValue($candidateUpdates, 'applied_department_name', $appliedDepartmentName !== '' ? $appliedDepartmentName : null, $isFinalSubmit || $appliedDepartmentName !== '');
                $this->mergeCandidateValue($candidateUpdates, 'applied_outlet_id', $appliedOutletId, $isFinalSubmit || array_key_exists('applied_outlet_id', $validated));
                $this->mergeCandidateValue($candidateUpdates, 'applied_outlet_name', $appliedOutletName !== '' ? $appliedOutletName : null, $isFinalSubmit || $appliedOutletName !== '');
                $candidate->fill($candidateUpdates)->save();
            }

            $missingSections = $profile->getMissingFields();
            $isComplete = empty($missingSections);
            $profile->completed_at = $isComplete ? now() : null;
            $profile->save();

            if ($isFinalSubmit && ! $isComplete) {
                $firstMissingStep = (int) min(array_map(
                    static fn (array $section): int => (int) ($section['step'] ?? 1),
                    array_values($missingSections)
                ));

                return back()
                    ->withInput($request->except($this->fileInputNames()))
                    ->with('missing_sections', $missingSections)
                    ->with('first_error_step', $firstMissingStep > 0 ? $firstMissingStep : 1)
                    ->with('error', 'Form belum lengkap. Mohon lengkapi field wajib yang ditandai terlebih dahulu.');
            }

            Log::info('Application form submit saved', $logContext + [
                'submit_mode' => $isFinalSubmit ? 'final' : 'draft',
                'applicant_profile_id' => $profile->id,
                'is_complete' => $isComplete,
                'completed_at' => optional($profile->completed_at)->toIso8601String(),
            ]);

            $response = back()->with('success', $isFinalSubmit ? 'Data berhasil dikirim. Terima kasih!' : 'Draft berhasil disimpan. Anda bisa melanjutkan nanti.');
            $docReminder = $this->missingDocumentReminder($profile, $this->hasAdministrativeDocumentStage($candidate));

            if ($isFinalSubmit && collect($docReminder)->contains(true)) {
                $response->with('doc_reminder', $docReminder);
            }

            return $response;
        } catch (RuntimeException|LogicException $exception) {
            Log::error('Application form upload/storage error', $logContext + [
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput($request->except($this->fileInputNames()))
                ->with('first_error_step', 1)
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Application form submit failed', $logContext + [
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
                'trace' => Str::limit($exception->getTraceAsString(), 4000),
            ]);

            return back()
                ->withInput($request->except($this->fileInputNames()))
                ->with('error', 'Terjadi kendala saat menyimpan data. Silakan coba beberapa saat lagi atau hubungi tim IT.');
        }
    }

    private function temporaryUploadJson(bool $ok, string $message, string $requestId, string $reason, int $status, array $errors = [], array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'ok' => $ok,
            'message' => $message,
            'request_id' => $requestId,
            'reason' => $reason,
            'errors' => $errors,
        ], $extra), $status);
    }

    private function publicUploadDefinition(array $definition): array
    {
        return [
            'label' => $definition['label'] ?? null,
            'temporary_directory' => $definition['temporary_directory'] ?? null,
            'final_directory' => $definition['final_directory'] ?? null,
            'allowed_extensions' => $definition['allowed_extensions'] ?? [],
            'allowed_mimes' => $definition['allowed_mimes'] ?? [],
            'max_kb' => $definition['max_kb'] ?? null,
            'max_bytes' => isset($definition['max_kb']) ? ((int) $definition['max_kb'] * 1024) : null,
            'disk' => 'public',
        ];
    }

    private function cleanRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $trimmed = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
            $hasAny = false;
            foreach ($trimmed as $v) {
                if ($v !== null && $v !== '' && $v !== []) {
                    $hasAny = true;
                    break;
                }
            }

            if ($hasAny) {
                $out[] = $trimmed;
            }
        }

        return array_values($out);
    }

    private function storeUploadedFileSafely(?UploadedFile $file, string $fieldName, string $directory, array $logContext): string
    {
        if ($file === null) {
            throw new RuntimeException($this->uploadFailureMessage($fieldName, UPLOAD_ERR_NO_FILE));
        }

        if (! $file->isValid()) {
            throw new RuntimeException($this->uploadFailureMessage($fieldName, $file->getError()));
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $filename = Str::uuid()->toString() . ($extension !== '' ? ".{$extension}" : '');
        $storedPath = (string) $file->storeAs($directory, $filename, 'public');

        if ($storedPath === '') {
            throw new RuntimeException('Upload ' . $this->fieldLabel($fieldName) . ' gagal disimpan ke server. Silakan coba lagi.');
        }

        Log::info('Application form upload stored', $logContext + [
            'field' => $fieldName,
            'directory' => $directory,
            'stored_path' => $storedPath,
            'disk' => 'public',
            'original_name' => $file->getClientOriginalName(),
            'client_extension' => strtolower((string) $file->getClientOriginalExtension()),
            'client_mime' => $file->getClientMimeType(),
            'detected_mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return $storedPath;
    }

    private function storeSignatureData(string $signatureData, array $logContext): string
    {
        if (! preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $signatureData)) {
            Log::warning('Application form signature rejected before storage', $logContext + [
                'reason' => 'invalid_signature_prefix',
            ]);
            throw new RuntimeException('Tanda tangan digital tidak valid. Silakan tanda tangani ulang.');
        }

        [$meta, $encoded] = explode(',', $signatureData, 2);
        $normalizedEncoded = preg_replace('/\s+/', '', $encoded);
        $binary = base64_decode((string) $normalizedEncoded, true);
        if ($binary === false) {
            Log::warning('Application form signature rejected before storage', $logContext + [
                'reason' => 'invalid_signature_base64',
            ]);
            throw new RuntimeException('Tanda tangan digital gagal dibaca. Silakan tanda tangani ulang.');
        }

        if ($binary === '') {
            Log::warning('Application form signature rejected before storage', $logContext + [
                'reason' => 'empty_signature_binary',
            ]);
            throw new RuntimeException('Tanda tangan digital kosong. Silakan tanda tangani ulang.');
        }

        $extension = str_contains($meta, 'jpeg') || str_contains($meta, 'jpg') ? 'jpg' : 'png';
        $path = 'applicants/signatures/' . Str::uuid()->toString() . '.' . $extension;
        $stored = Storage::disk('public')->put($path, $binary);
        if (! $stored) {
            Log::error('Application form signature failed to store', $logContext + [
                'reason' => 'signature_storage_failed',
                'target_path' => $path,
            ]);
            throw new RuntimeException('Tanda tangan digital gagal disimpan ke server. Silakan coba lagi.');
        }

        Log::info('Application form signature stored', $logContext + [
            'stored_path' => $path,
            'extension' => $extension,
            'size_bytes' => strlen($binary),
        ]);

        return $path;
    }

    private function loadPositions(): EloquentCollection
    {
        if (! Schema::hasTable('positions')) {
            return new EloquentCollection();
        }

        return Position::query()->orderBy('name')->get(['id', 'name']);
    }

    private function loadDepartments(): EloquentCollection
    {
        if (! Schema::hasTable('departments')) {
            return new EloquentCollection();
        }

        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    private function loadOutlets(): EloquentCollection
    {
        if (! Schema::hasTable('outlets')) {
            return new EloquentCollection();
        }

        return Outlet::query()->operational()->orderBy('name')->get(['id', 'name', 'brand_name']);
    }

    private function resolveCandidateForUser($user, ApplicantProfile $profile): ?Candidate
    {
        $email = trim((string) $user->email);
        $nik = trim((string) data_get($profile->personal_json, 'ktp_number', ''));

        return Candidate::query()
            ->when($user->id, fn ($query) => $query->where('user_id', $user->id))
            ->when($email !== '', fn ($query) => $query->orWhere('email', $email))
            ->when($nik !== '', fn ($query) => $query->orWhere('nik', $nik))
            ->latest('id')
            ->first();
    }

    private function hasAdministrativeDocumentStage(?Candidate $candidate): bool
    {
        return in_array((string) ($candidate?->status ?? ''), [Candidate::STATUS_SHORTLISTED, Candidate::STATUS_ACCEPTED], true);
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;
        return $normalized > 0 ? $normalized : null;
    }

    private function preserveExistingDocumentPath(array &$documents, string $key, mixed $existingPath, string $directory): void
    {
        if (trim((string) ($documents[$key] ?? '')) !== '') {
            return;
        }

        $path = trim((string) $existingPath);
        $prefix = trim($directory, '/') . '/';

        if ($path === '' || ! Str::startsWith($path, $prefix) || str_contains($path, '..')) {
            return;
        }

        $documents[$key] = $path;
    }

    private function missingDocumentReminder(ApplicantProfile $profile, bool $includeAdministrativeDocuments): array
    {
        $personal = $profile->normalizedPersonalJson();
        $graduationDocuments = (array) data_get($personal, 'graduation_documents', []);

        return [
            'skck' => blank(data_get($personal, 'skck_latest_path')),
            'ijazah' => $includeAdministrativeDocuments && blank(data_get($graduationDocuments, 'diploma_path')),
            'transkrip' => $includeAdministrativeDocuments && blank(data_get($graduationDocuments, 'transcript_path')),
            'akta_lahir' => $includeAdministrativeDocuments && blank(data_get($graduationDocuments, 'birth_certificate_path')),
        ];
    }

    private function mergeValidatedScalar(array &$target, array $validated, string $key, bool $allowEmpty): void
    {
        if (! array_key_exists($key, $validated)) {
            return;
        }

        $value = $validated[$key];
        if (! $allowEmpty && ($value === null || (is_string($value) && trim($value) === ''))) {
            return;
        }

        $target[$key] = is_string($value) ? trim($value) : $value;
    }

    private function mergeResolvedValue(array &$target, string $key, mixed $value, bool $shouldMerge): void
    {
        if (! $shouldMerge) {
            return;
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            unset($target[$key]);
            return;
        }

        $target[$key] = is_string($value) ? trim($value) : $value;
    }

    private function mergeCandidateValue(array &$target, string $key, mixed $value, bool $shouldMerge): void
    {
        if (! $shouldMerge) {
            return;
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        $target[$key] = is_string($value) ? trim($value) : $value;
    }

    private function safeTokenHash(string $token): ?string
    {
        $token = trim($token);

        return $token === '' ? null : substr(hash('sha256', $token), 0, 12);
    }

    private function buildLogContext(Request $request, string $requestId, ApplicantProfile $profile, ?Candidate $candidate): array
    {
        return [
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
            'candidate_id' => $candidate?->id,
            'candidate_status' => $candidate?->status,
            'applicant_profile_id' => $profile->id,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'submit_mode' => $request->boolean('final_submit') ? 'final' : 'draft',
            'final_submit' => $request->boolean('final_submit'),
            'current_step' => $request->input('current_step'),
            'field_names' => array_values(array_diff(array_keys($request->except($this->fileInputNames())), ['signature_data'])),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'content_length' => $request->server('CONTENT_LENGTH'),
            'files' => $this->collectFileDiagnostics($request),
            'temporary_tokens' => [
                'photo_ktp_file' => $this->safeTokenHash((string) $request->input('photo_ktp_file_token', '')),
                'scan_ktp_file' => $this->safeTokenHash((string) $request->input('scan_ktp_file_token', '')),
                'cv_file' => $this->safeTokenHash((string) $request->input('cv_file_token', '')),
            ],
            'storage_diagnostics' => [
                'public_storage_exists' => file_exists(public_path('storage')),
                'public_storage_link' => is_link(public_path('storage')) || is_dir(public_path('storage')),
                'public_disk_root' => Storage::disk('public')->path(''),
                'public_disk_writable' => is_writable(Storage::disk('public')->path('')),
            ],
        ];
    }

    private function fieldLabel(string $fieldName): string
    {
        return match ($fieldName) {
            'photo_ktp_file' => 'pas foto',
            'scan_ktp_file' => 'scan KTP',
            'cv_file' => 'CV',
            'skck_file' => 'SKCK',
            'graduation_diploma_file' => 'ijazah terakhir',
            'graduation_transcript_file' => 'transkrip nilai',
            'graduation_birth_certificate_file' => 'akta kelahiran',
            default => 'dokumen',
        };
    }

    private function uploadFailureMessage(string $fieldName, int $errorCode): string
    {
        $label = $this->fieldLabel($fieldName);

        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran ' . $label . ' melebihi batas upload server. Silakan kompres file lalu coba lagi.',
            UPLOAD_ERR_PARTIAL => ucfirst($label) . ' gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
            UPLOAD_ERR_NO_FILE => ucfirst($label) . ' belum dipilih. Silakan pilih file lalu coba lagi.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Upload ' . $label . ' gagal diproses di server. Silakan coba lagi beberapa saat atau hubungi tim IT.',
            default => 'Upload ' . $label . ' gagal diproses. Pastikan file valid lalu coba lagi.',
        };
    }

    private function collectFileDiagnostics(Request $request): array
    {
        $diagnostics = [];

        foreach ($this->fileInputNames() as $field) {
            $files = $field === 'supporting_files'
                ? array_values((array) $request->file('supporting_files', []))
                : [$request->file($field)];

            $diagnostics[$field] = collect($files)
                ->filter(fn ($file) => $file instanceof UploadedFile)
                ->values()
                ->map(fn (UploadedFile $file) => [
                    'original_name' => $file->getClientOriginalName(),
                    'client_extension' => strtolower((string) $file->getClientOriginalExtension()),
                    'client_mime' => $file->getClientMimeType(),
                    'detected_mime' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'upload_error' => $file->getError(),
                    'is_valid' => $file->isValid(),
                ])
                ->all();
        }

        return $diagnostics;
    }

    private function fileInputNames(): array
    {
        return [
            'photo_ktp_file',
            'scan_ktp_file',
            'cv_file',
            'skck_file',
            'graduation_diploma_file',
            'graduation_transcript_file',
            'graduation_birth_certificate_file',
            'supporting_files',
        ];
    }

    private function resolveTemporaryUploadState(Request $request, ApplicationFormTemporaryUploadService $temporaryUploadService, int $userId): array
    {
        $oldInput = (array) $request->session()->getOldInput();
        $state = [];

        foreach (ApplicationFormTemporaryUploadService::supportedFields() as $field) {
            $token = trim((string) ($oldInput[$field . '_token'] ?? ''));
            $state[$field] = $token !== ''
                ? $temporaryUploadService->findTemporaryUpload($token, $field, $userId)
                : null;
        }

        return $state;
    }
}
