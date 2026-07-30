<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ApplicantProfile;
use App\Models\Candidate;
use App\Services\ApplicationFormTemporaryUploadService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicationFormUpdateRequest extends FormRequest
{
    private const SIGNATURE_ALLOWED_MIME_TYPES = ['png', 'jpg', 'jpeg'];
    private const SIGNATURE_MAX_BYTES = 2_500_000;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isFinalSubmit = $this->isFinalSubmit();
        $profile = ApplicantProfile::withTrashed()->where('user_id', $this->user()?->id)->first();
        $personal = is_array($profile?->personal_json) ? $profile->personal_json : [];
        $hasPhoto = trim((string) ($profile?->photo_path ?? '')) !== '' || trim((string) $this->input('photo_ktp_file_token', '')) !== '' || $this->file('photo_ktp_file') instanceof UploadedFile;
        $hasKtp = trim((string) ($profile?->ktp_path ?? '')) !== '' || trim((string) $this->input('scan_ktp_file_token', '')) !== '' || $this->file('scan_ktp_file') instanceof UploadedFile;
        $hasCv = trim((string) ($profile?->cv_path ?? '')) !== '' || trim((string) $this->input('cv_file_token', '')) !== '' || $this->file('cv_file') instanceof UploadedFile;
        $signatureCleared = $this->boolean('signature_cleared');
        $hasSignature = ! $signatureCleared && trim((string) data_get($personal, 'signature_path', '')) !== '';
        $hasSkck = trim((string) data_get($personal, 'skck_latest_path', '')) !== '';
        $hasDiploma = trim((string) data_get($personal, 'graduation_documents.diploma_path', '')) !== '';
        $hasTranscript = trim((string) data_get($personal, 'graduation_documents.transcript_path', '')) !== '';
        $hasBirthCertificate = trim((string) data_get($personal, 'graduation_documents.birth_certificate_path', '')) !== '';
        $hasSupporting = collect((array) data_get($personal, 'graduation_documents.supporting_files', []))->filter()->isNotEmpty();
        $positionsAvailable = Schema::hasTable('positions') && \App\Models\Position::query()->exists();
        $hasDepartmentsTable = Schema::hasTable('departments');
        $hasOutletsTable = Schema::hasTable('outlets');
        $requiresAdminDocs = $this->requiresAdministrativeDocuments();

        $imageOrPdfRules = ['file', 'max:4096'];

        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'ktp_number' => ['required', 'string', 'max:50'],
            'place_of_birth' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date'],
            'time_of_birth' => ['required', 'date_format:H:i'],
            'gender' => ['required', 'string'],
            'religion' => ['required', 'string'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'marital_status' => ['required', 'string', Rule::in(['Single', 'Menikah', 'Duda', 'Janda'])],
            'marriage_date' => ['nullable', 'date'],
            'whatsapp' => ['required', 'string', 'max:20'],
            'phone_number' => ['required', 'string', 'max:20'],
            'salary_expectation' => ['required', 'numeric', 'min:0'],
            'preferred_job_scope' => ['nullable', Rule::in(['Managerial', 'Tekhnikal', 'Klerikal', 'Lainnya'])],
            'preferred_job_scope_other' => ['nullable', 'string', 'max:255'],
            'preferred_work_environment' => ['nullable', Rule::in(['Kantor', 'Luar Kantor', 'Pabrik', 'Laboratorium', 'Mall', 'Lainnya'])],
            'preferred_work_environment_other' => ['nullable', 'string', 'max:255'],
            'applied_position_id' => $positionsAvailable ? ['nullable', 'integer', 'exists:positions,id'] : ['nullable'],
            'applied_position_name' => ['required', 'string', 'max:255'],
            'applied_department_id' => $hasDepartmentsTable ? ['nullable', 'integer', 'exists:departments,id'] : ['nullable'],
            'applied_outlet_id' => $hasOutletsTable ? ['nullable', 'integer', 'exists:outlets,id'] : ['nullable'],
            'willing_out_of_town' => ['required', Rule::in(['Ya', 'Tidak'])],
            'willing_outside_java' => ['required', Rule::in(['Ya', 'Tidak'])],
            'willing_shift' => ['required', Rule::in(['Ya', 'Tidak'])],
            'willing_overtime' => ['required', Rule::in(['Ya', 'Tidak'])],
            'is_smoker' => ['required', Rule::in(['Ya', 'Tidak'])],
            'has_computer_skill' => ['required', Rule::in(['Ya', 'Tidak'])],
            'wears_glasses' => ['required', Rule::in(['Ya', 'Tidak'])],
            'glasses_right_eye' => ['nullable', 'string', 'max:50'],
            'glasses_left_eye' => ['nullable', 'string', 'max:50'],
            'join_reason' => ['required', 'string', 'max:4000'],
            'company_relation_note' => ['required', 'string', 'max:4000'],
            'career_goal' => ['required', 'string', 'max:4000'],
            'additional_information' => ['nullable', 'string', 'max:4000'],
            'available_start_date' => ['required', 'date'],
            'honesty_statement' => ['required', 'string', 'min:60', 'max:5000'],

            'photo_ktp_file' => array_merge([$hasPhoto ? 'nullable' : 'required'], $imageOrPdfRules),
            'scan_ktp_file' => array_merge([$hasKtp ? 'nullable' : 'required'], $imageOrPdfRules),
            'cv_file' => ['nullable', Rule::requiredIf(! $hasCv), 'file', 'max:5120'],
            'photo_ktp_file_token' => ['nullable', 'string', 'max:120'],
            'scan_ktp_file_token' => ['nullable', 'string', 'max:120'],
            'cv_file_token' => ['nullable', 'string', 'max:120'],
            'signature_data' => [$hasSignature ? 'nullable' : 'required', 'string'],
            'signature_cleared' => ['nullable', 'boolean'],

            'ktp_address' => ['required', 'string', 'max:1000'],
            'ktp_rt' => ['required', 'string', 'max:10'],
            'ktp_rw' => ['required', 'string', 'max:10'],
            'ktp_kelurahan' => ['required', 'string', 'max:255'],
            'ktp_kecamatan' => ['required', 'string', 'max:255'],
            'ktp_city' => ['required', 'string', 'max:255'],
            'ktp_province' => ['nullable', 'string', 'max:255'],
            'domicile_address' => ['required', 'string', 'max:1000'],
            'domicile_rt' => ['required', 'string', 'max:10'],
            'domicile_rw' => ['required', 'string', 'max:10'],
            'domicile_kelurahan' => ['required', 'string', 'max:255'],
            'domicile_kecamatan' => ['required', 'string', 'max:255'],
            'domicile_city' => ['required', 'string', 'max:255'],
            'domicile_province' => ['nullable', 'string', 'max:255'],

            'families' => ['required', 'array', 'min:2'],
            'families.*.relation' => ['required', 'string', 'max:100'],
            'families.*.name' => ['required', 'string', 'max:255'],
            'families.*.gender' => ['required', 'string', 'max:50'],
            'families.*.dob' => ['nullable', 'date'],
            'families.*.education' => ['nullable', 'string', 'max:100'],
            'families.*.job' => ['nullable', 'string', 'max:255'],
            'families.*.status_note' => ['nullable', 'string', 'max:100'],

            'emergency_contacts' => ['required', 'array', 'min:2'],
            'emergency_contacts.*.name' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.relation' => ['required', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['required', 'string', 'max:25'],
            'emergency_contacts.*.address' => ['required', 'string', 'max:500'],

            'educations' => ['required', 'array', 'min:3'],
            'educations.*.level' => ['required', 'string', 'max:50'],
            'educations.*.school' => ['required', 'string', 'max:255'],
            'educations.*.major' => ['required', 'string', 'max:255'],
            'educations.*.year_in' => ['required', 'numeric'],
            'educations.*.year_out' => ['required', 'numeric'],
            'educations.*.gpa' => ['nullable', 'string', 'max:20'],

            'work_experiences' => ['required', 'array', 'min:1'],
            'work_experiences.*.company' => ['required', 'string', 'max:255'],
            'work_experiences.*.position' => ['required', 'string', 'max:255'],
            'work_experiences.*.date_start' => ['required', 'date'],
            'work_experiences.*.date_end' => ['nullable', 'date'],
            'work_experiences.*.salary' => ['required', 'string', 'max:100'],
            'work_experiences.*.reason' => ['required', 'string', 'max:255'],

            'reference_contacts' => ['required', 'array', 'min:2'],
            'reference_contacts.*.name' => ['required', 'string', 'max:255'],
            'reference_contacts.*.relation' => ['required', 'string', 'max:100'],
            'reference_contacts.*.company' => ['required', 'string', 'max:255'],
            'reference_contacts.*.phone' => ['required', 'string', 'max:25'],

            'medical_histories' => ['required', 'array', 'min:1'],
            'medical_histories.*.illness' => ['required', 'string', 'max:255'],
            'medical_histories.*.year' => ['required', 'numeric'],
            'medical_histories.*.hospitalized' => ['required', 'string', 'max:10'],
            'medical_histories.*.note' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['required', 'numeric', 'min:1'],
            'height_cm' => ['required', 'numeric', 'min:1'],
            'had_accident' => ['required', Rule::in(['Ya', 'Tidak'])],
            'accident_year' => ['nullable', 'numeric'],
            'accident_type' => ['nullable', 'string', 'max:255'],
            'accident_effect' => ['nullable', 'string', 'max:255'],
            'police_record' => ['required', Rule::in(['Ya', 'Tidak'])],
            'police_record_case' => ['nullable', 'string', 'max:255'],
            'police_record_year' => ['nullable', 'numeric'],
            'police_record_location' => ['nullable', 'string', 'max:255'],
            'psychology_test' => ['required', Rule::in(['Ya', 'Tidak'])],
            'psychology_test_year' => ['nullable', 'numeric'],
            'psychology_test_location' => ['nullable', 'string', 'max:255'],
            'psychology_test_purpose' => ['nullable', 'string', 'max:255'],
            'skck_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            'courses' => ['nullable', 'array'],
            'languages' => ['nullable', 'array'],
            'organizations' => ['nullable', 'array'],
            'social_medias' => ['nullable', 'array'],

            'graduation_diploma_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'graduation_transcript_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'graduation_birth_certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'supporting_files' => ['nullable', 'array', 'max:5'],
            'supporting_files.*' => array_merge([Rule::requiredIf($requiresAdminDocs && ! $hasSupporting)], $imageOrPdfRules),

            'final_submit' => ['nullable', 'boolean'],
            'current_step' => ['nullable', 'integer', 'min:1', 'max:6'],
        ];

        return $isFinalSubmit ? $rules : $this->draftRules($rules);
    }

    public function messages(): array
    {
        return [
            'time_of_birth.required' => 'Jam lahir wajib diisi.',
            'phone_number.required' => 'Nomor telepon / HP aktif wajib diisi.',
            'educations.min' => 'Riwayat pendidikan wajib diisi minimal 3 jenjang.',
            'emergency_contacts.min' => 'Kontak darurat wajib diisi minimal 2 kontak.',
            'reference_contacts.min' => 'Kontak referensi wajib diisi minimal 2 kontak.',
            'signature_data.required' => 'Tanda tangan digital peserta wajib dibuat.',
            'signature_data.string' => 'Format tanda tangan digital tidak dapat diproses.',
            'graduation_diploma_file.required' => 'Ijazah terakhir wajib diunggah saat lolos administrasi.',
            'graduation_transcript_file.required' => 'Transkrip nilai wajib diunggah saat lolos administrasi.',
            'graduation_birth_certificate_file.required' => 'Akta kelahiran wajib diunggah saat lolos administrasi.',
            'supporting_files.max' => 'Dokumen pendukung maksimal 5 file.',
            'skck_file.required' => 'SKCK terbaru wajib diunggah sesuai ketentuan.',
            'photo_ktp_file.required' => 'Pas foto wajib diunggah.',
            'photo_ktp_file.max' => 'Ukuran pas foto maksimal 4 MB.',
            'photo_ktp_file.uploaded' => 'Pas foto gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
            'scan_ktp_file.required' => 'Scan KTP wajib diunggah.',
            'scan_ktp_file.max' => 'Ukuran scan KTP maksimal 4 MB.',
            'scan_ktp_file.uploaded' => 'Scan KTP gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
            'cv_file.required' => 'CV wajib diunggah dalam format PDF.',
            'cv_file.max' => 'Ukuran CV maksimal 5 MB.',
            'cv_file.uploaded' => 'CV gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
            'skck_file.max' => 'Ukuran SKCK terbaru maksimal 5 MB.',
            'skck_file.uploaded' => 'SKCK terbaru gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
            'graduation_diploma_file.max' => 'Ukuran ijazah terakhir maksimal 5 MB.',
            'graduation_diploma_file.uploaded' => 'Ijazah terakhir gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
            'graduation_transcript_file.max' => 'Ukuran transkrip nilai maksimal 5 MB.',
            'graduation_transcript_file.uploaded' => 'Transkrip nilai gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
            'graduation_birth_certificate_file.max' => 'Ukuran akta kelahiran maksimal 5 MB.',
            'graduation_birth_certificate_file.uploaded' => 'Akta kelahiran gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
            'supporting_files.*.max' => 'Ukuran dokumen pendukung maksimal 4 MB per file.',
            'supporting_files.*.uploaded' => 'Salah satu dokumen pendukung gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'nama lengkap',
            'ktp_number' => 'NIK',
            'time_of_birth' => 'jam lahir',
            'phone_number' => 'nomor telepon / HP aktif',
            'whatsapp' => 'nomor WhatsApp aktif',
            'salary_expectation' => 'ekspektasi gaji',
            'honesty_statement' => 'pernyataan kejujuran',
            'join_reason' => 'alasan bergabung',
            'career_goal' => 'target karir',
            'available_start_date' => 'tanggal siap bergabung',
            'weight_kg' => 'berat badan',
            'height_cm' => 'tinggi badan',
            'skck_file' => 'SKCK terbaru',
        ];
    }

    protected function prepareForValidation(): void
    {
        $requestId = (string) ($this->attributes->get('application_form_request_id') ?: Str::uuid());
        $this->attributes->set('application_form_request_id', $requestId);
        $this->attributes->set('application_form_submitted_fields', array_values(array_keys($this->all())));

        $this->merge([
            'families' => $this->filterRows($this->input('families', [])),
            'emergency_contacts' => $this->filterRows($this->input('emergency_contacts', [])),
            'educations' => $this->filterRows($this->input('educations', [])),
            'work_experiences' => $this->filterRows($this->input('work_experiences', [])),
            'reference_contacts' => $this->filterRows($this->input('reference_contacts', [])),
            'medical_histories' => $this->filterRows($this->input('medical_histories', [])),
            'courses' => $this->filterRows($this->input('courses', [])),
            'languages' => $this->filterRows($this->input('languages', [])),
            'organizations' => $this->filterRows($this->input('organizations', [])),
            'social_medias' => $this->filterRows($this->input('social_medias', [])),
            'final_submit' => $this->boolean('final_submit'),
            'signature_data' => trim((string) $this->input('signature_data', '')),
            'signature_cleared' => $this->boolean('signature_cleared'),
        ]);

        Log::info('Application form submit received', $this->buildRequestLogContext($requestId));
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->isFinalSubmit()) {
                $this->validateConditionalInputs($validator);
            }
            $this->validateDocumentUploads($validator);
            $this->validateTemporaryUploadTokens($validator);
            $this->validateUploadLimitSymptoms($validator);
            $this->validateSignaturePayload($validator);
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $errorKeys = array_keys($validator->errors()->messages());
        $requestId = (string) ($this->attributes->get('application_form_request_id') ?: Str::uuid());

        Log::warning('Application form validation failed', $this->buildRequestLogContext($requestId) + [
            'validation_error_keys' => $errorKeys,
            'validation_errors' => $validator->errors()->messages(),
        ]);

        $response = redirect()
            ->back()
            ->withInput($this->except($this->fileInputNames()))
            ->withErrors($validator)
            ->with('error', $this->isFinalSubmit()
                ? 'Periksa kembali field yang ditandai merah sebelum mengirim form.'
                : 'Draft belum berhasil disimpan. Periksa format data atau dokumen yang ditandai.')
            ->with('first_error_step', $this->inferFirstErrorStep($errorKeys));

        throw new ValidationException($validator, $response);
    }

    private function validateConditionalInputs(Validator $validator): void
    {
        if ($this->input('preferred_job_scope') === 'Lainnya' && trim((string) $this->input('preferred_job_scope_other')) === '') {
            $validator->errors()->add('preferred_job_scope_other', 'Ruang lingkup pekerjaan lainnya wajib dijelaskan.');
        }

        if ($this->input('preferred_work_environment') === 'Lainnya' && trim((string) $this->input('preferred_work_environment_other')) === '') {
            $validator->errors()->add('preferred_work_environment_other', 'Lingkungan kerja lainnya wajib dijelaskan.');
        }

        if ($this->input('wears_glasses') === 'Ya') {
            if (trim((string) $this->input('glasses_right_eye')) === '') {
                $validator->errors()->add('glasses_right_eye', 'Detail ukuran mata kanan wajib diisi jika memakai kacamata.');
            }
            if (trim((string) $this->input('glasses_left_eye')) === '') {
                $validator->errors()->add('glasses_left_eye', 'Detail ukuran mata kiri wajib diisi jika memakai kacamata.');
            }
        }

        if ($this->input('had_accident') === 'Ya') {
            foreach (['accident_year', 'accident_type', 'accident_effect'] as $field) {
                if (trim((string) $this->input($field)) === '') {
                    $validator->errors()->add($field, 'Detail kecelakaan wajib diisi jika Anda menjawab Ya.');
                }
            }
        }

        if ($this->input('police_record') === 'Ya') {
            foreach (['police_record_case', 'police_record_year', 'police_record_location'] as $field) {
                if (trim((string) $this->input($field)) === '') {
                    $validator->errors()->add($field, 'Detail urusan kepolisian wajib diisi jika Anda menjawab Ya.');
                }
            }
        }

        if ($this->input('psychology_test') === 'Ya') {
            foreach (['psychology_test_year', 'psychology_test_location', 'psychology_test_purpose'] as $field) {
                if (trim((string) $this->input($field)) === '') {
                    $validator->errors()->add($field, 'Detail evaluasi psikologi wajib diisi jika Anda menjawab Ya.');
                }
            }
        }

        $familyRelations = collect((array) $this->input('families', []))
            ->map(fn ($row) => mb_strtolower(trim((string) ($row['relation'] ?? ''))))
            ->filter()
            ->values();

        foreach (['ayah', 'ibu'] as $requiredRelation) {
            if (! $familyRelations->contains($requiredRelation)) {
                $validator->errors()->add('families', 'Data keluarga wajib memuat ' . strtoupper($requiredRelation) . '.');
            }
        }

        if ($this->input('marital_status') === 'Menikah' && ! $familyRelations->contains(function ($relation) {
            return in_array($relation, ['suami', 'istri'], true);
        })) {
            $validator->errors()->add('families', 'Jika status menikah, data suami/istri wajib dicantumkan.');
        }
    }

    private function validateDocumentUploads(Validator $validator): void
    {
        $rules = [
            'photo_ktp_file' => [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
                'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                'invalid_message' => 'Format pas foto harus JPG, JPEG, PNG, WEBP, atau PDF.',
                'heic_message' => 'Pas foto dari iPhone terdeteksi berformat HEIC/HEIF. Ubah ke JPG, PNG, WEBP, atau PDF lalu unggah kembali.',
            ],
            'scan_ktp_file' => [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
                'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                'invalid_message' => 'Format scan KTP harus JPG, JPEG, PNG, WEBP, atau PDF.',
                'heic_message' => 'Scan KTP dari iPhone terdeteksi berformat HEIC/HEIF. Ubah ke JPG, PNG, WEBP, atau PDF lalu unggah kembali.',
            ],
            'cv_file' => [
                'allowed_extensions' => ['pdf'],
                'allowed_mimes' => ['application/pdf'],
                'invalid_message' => 'CV harus diunggah dalam format PDF.',
                'heic_message' => 'CV dari iPhone terdeteksi berformat HEIC/HEIF. Simpan atau ekspor CV ke PDF lalu unggah kembali.',
            ],
            'skck_file' => [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                'invalid_message' => 'Format SKCK terbaru harus JPG, JPEG, PNG, atau PDF.',
                'heic_message' => 'SKCK terbaru dari iPhone terdeteksi berformat HEIC/HEIF. Ubah ke JPG, PNG, atau PDF lalu unggah kembali.',
            ],
            'graduation_diploma_file' => [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                'invalid_message' => 'Format ijazah terakhir harus JPG, JPEG, PNG, atau PDF.',
                'heic_message' => 'Ijazah terakhir dari iPhone terdeteksi berformat HEIC/HEIF. Ubah ke JPG, PNG, atau PDF lalu unggah kembali.',
            ],
            'graduation_transcript_file' => [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                'invalid_message' => 'Format transkrip nilai harus JPG, JPEG, PNG, atau PDF.',
                'heic_message' => 'Transkrip nilai dari iPhone terdeteksi berformat HEIC/HEIF. Ubah ke JPG, PNG, atau PDF lalu unggah kembali.',
            ],
            'graduation_birth_certificate_file' => [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                'allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],
                'invalid_message' => 'Format akta kelahiran harus JPG, JPEG, PNG, atau PDF.',
                'heic_message' => 'Akta kelahiran dari iPhone terdeteksi berformat HEIC/HEIF. Ubah ke JPG, PNG, atau PDF lalu unggah kembali.',
            ],
        ];

        foreach ($rules as $field => $rule) {
            $file = $this->file($field);
            if ($file instanceof UploadedFile) {
                $this->validateDocumentUpload($validator, $field, $file, $rule);
            }
        }

        foreach ((array) $this->file('supporting_files', []) as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $this->validateDocumentUpload($validator, 'supporting_files.' . $index, $file, [
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
                'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                'invalid_message' => 'Format dokumen pendukung harus JPG, JPEG, PNG, WEBP, atau PDF.',
                'heic_message' => 'Dokumen pendukung dari iPhone terdeteksi berformat HEIC/HEIF. Ubah ke JPG, PNG, WEBP, atau PDF lalu unggah kembali.',
            ]);
        }
    }

    private function validateUploadLimitSymptoms(Validator $validator): void
    {
        $effectiveLimit = \App\Support\ApplicationFormUploadLimit::effectiveBytes();
        $contentLength = (int) $this->server('CONTENT_LENGTH');

        if ($effectiveLimit <= 0 || $contentLength <= 0 || $contentLength <= $effectiveLimit) {
            return;
        }

        $problemFields = collect(['photo_ktp_file', 'scan_ktp_file', 'cv_file'])
            ->filter(function (string $field): bool {
                return trim((string) $this->input($field . '_token', '')) === ''
                    && ! ($this->file($field) instanceof UploadedFile)
                    && trim((string) data_get($this->all(), $field, '')) === '';
            })
            ->values()
            ->all();

        if ($problemFields === []) {
            return;
        }

        foreach ($problemFields as $field) {
            $validator->errors()->add($field, 'Upload tidak sampai ke Laravel karena ukuran request melebihi batas server. Cek upload_max_filesize, post_max_size, dan client_max_body_size di server production.');
        }
    }

    private function validateTemporaryUploadTokens(Validator $validator): void
    {
        $userId = (int) ($this->user()?->id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $temporaryUploadService = app(ApplicationFormTemporaryUploadService::class);
        foreach (ApplicationFormTemporaryUploadService::supportedFields() as $field) {
            $token = trim((string) $this->input($field . '_token', ''));
            if ($token === '') {
                continue;
            }

            if ($temporaryUploadService->findTemporaryUpload($token, $field, $userId) !== null) {
                continue;
            }

            $label = match ($field) {
                'photo_ktp_file' => 'pas foto',
                'scan_ktp_file' => 'scan KTP',
                'cv_file' => 'CV',
                default => 'dokumen',
            };

            if (! $this->isFinalSubmit()) {
                Log::warning('Application form draft ignored unavailable temporary upload token', [
                    'request_id' => $this->attributes->get('application_form_request_id'),
                    'user_id' => $userId,
                    'field' => $field,
                    'token_hash' => $this->safeTokenHash($token),
                    'has_file_fallback' => $this->file($field) instanceof UploadedFile,
                ]);
                continue;
            }

            $validator->errors()->add($field, 'Upload sementara untuk ' . $label . ' sudah tidak tersedia. Silakan pilih dan unggah ulang file tersebut.');
        }
    }

    private function validateSignaturePayload(Validator $validator): void
    {
        $signature = trim((string) $this->input('signature_data', ''));
        if ($signature === '') {
            return;
        }

        if (! preg_match('/^data:image\/(?P<mime>png|jpg|jpeg);base64,(?P<data>[A-Za-z0-9+\/=\r\n]+)$/', $signature, $matches)) {
            $validator->errors()->add('signature_data', 'Format tanda tangan digital tidak valid. Silakan tanda tangani ulang.');
            return;
        }

        $mime = strtolower((string) ($matches['mime'] ?? ''));
        if (! in_array($mime, self::SIGNATURE_ALLOWED_MIME_TYPES, true)) {
            $validator->errors()->add('signature_data', 'Format tanda tangan digital belum didukung. Silakan gunakan tanda tangan bawaan form ini.');
            return;
        }

        $encoded = preg_replace('/\s+/', '', (string) ($matches['data'] ?? ''));
        if ($encoded === '') {
            $validator->errors()->add('signature_data', 'Data tanda tangan digital kosong. Silakan tanda tangani ulang.');
            return;
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            $validator->errors()->add('signature_data', 'Tanda tangan digital gagal dibaca. Silakan tanda tangani ulang.');
            return;
        }

        if ($binary === '') {
            $validator->errors()->add('signature_data', 'Tanda tangan digital kosong. Silakan buat ulang tanda tangan Anda.');
            return;
        }

        if (strlen($binary) > self::SIGNATURE_MAX_BYTES) {
            $validator->errors()->add('signature_data', 'Ukuran tanda tangan digital terlalu besar. Silakan hapus lalu buat ulang dengan tanda tangan yang lebih ringkas.');
        }
    }

    private function validateDocumentUpload(Validator $validator, string $field, UploadedFile $file, array $rule): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $clientMime = strtolower((string) $file->getClientMimeType());
        $detectedMime = strtolower((string) $file->getMimeType());

        if ($this->isHeicLike($extension, $clientMime, $detectedMime)) {
            $validator->errors()->add($field, $rule['heic_message']);
            return;
        }

        $hasAllowedExtension = $extension !== '' && in_array($extension, $rule['allowed_extensions'], true);
        $hasAllowedMime = ($clientMime !== '' && in_array($clientMime, $rule['allowed_mimes'], true))
            || ($detectedMime !== '' && in_array($detectedMime, $rule['allowed_mimes'], true));

        if (! $hasAllowedExtension && ! $hasAllowedMime) {
            $validator->errors()->add($field, $rule['invalid_message']);
        }
    }

    private function isHeicLike(string $extension, string $clientMime, string $detectedMime): bool
    {
        if (in_array($extension, ['heic', 'heif'], true)) {
            return true;
        }

        foreach ([$clientMime, $detectedMime] as $mime) {
            if ($mime !== '' && (str_contains($mime, 'heic') || str_contains($mime, 'heif'))) {
                return true;
            }
        }

        return false;
    }

    private function requiresAdministrativeDocuments(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $candidate = Candidate::query()
            ->when($user->id, fn ($query) => $query->where('user_id', $user->id))
            ->orWhere('email', trim((string) $user->email))
            ->first();

        return in_array((string) ($candidate?->status ?? ''), [Candidate::STATUS_SHORTLISTED, Candidate::STATUS_ACCEPTED], true);
    }

    private function isFinalSubmit(): bool
    {
        return $this->boolean('final_submit');
    }

    private function draftRules(array $finalRules): array
    {
        $draftRules = [];

        foreach ($finalRules as $field => $rules) {
            $rules = is_array($rules) ? $rules : [$rules];
            $isArrayField = in_array('array', $rules, true);
            $filtered = [];
            $hasNullable = false;

            foreach ($rules as $rule) {
                if ($rule instanceof \Illuminate\Validation\Rules\RequiredIf) {
                    continue;
                }

                if ($rule === 'required' || (is_string($rule) && str_starts_with($rule, 'required'))) {
                    continue;
                }

                if ($isArrayField && is_string($rule) && str_starts_with($rule, 'min:')) {
                    continue;
                }

                if ($field === 'honesty_statement' && is_string($rule) && str_starts_with($rule, 'min:')) {
                    continue;
                }

                if ($rule === 'nullable') {
                    $hasNullable = true;
                }

                $filtered[] = $rule;
            }

            if (! $hasNullable) {
                array_unshift($filtered, 'nullable');
            }

            $draftRules[$field] = $filtered;
        }

        return $draftRules;
    }

    private function filterRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
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
                $out[] = $trimmed;
            }
        }

        return array_values($out);
    }

    private function inferFirstErrorStep(array $errorKeys): int
    {
        foreach ($errorKeys as $key) {
            if ($this->matchesStep($key, [
                'full_name', 'ktp_number', 'place_of_birth', 'date_of_birth', 'time_of_birth', 'gender', 'religion',
                'blood_type', 'marital_status', 'marriage_date', 'whatsapp', 'phone_number', 'photo_ktp_file',
                'scan_ktp_file', 'cv_file', 'photo_ktp_file_token', 'scan_ktp_file_token', 'cv_file_token',
            ])) {
                return 1;
            }

            if ($this->matchesStep($key, [
                'ktp_address', 'ktp_rt', 'ktp_rw', 'ktp_kelurahan', 'ktp_kecamatan', 'ktp_city', 'ktp_province',
                'domicile_address', 'domicile_rt', 'domicile_rw', 'domicile_kelurahan', 'domicile_kecamatan',
                'domicile_city', 'domicile_province', 'families', 'emergency_contacts',
            ])) {
                return 2;
            }

            if ($this->matchesStep($key, ['educations', 'courses', 'languages'])) {
                return 3;
            }

            if ($this->matchesStep($key, [
                'applied_position_id', 'applied_position_name', 'applied_department_id', 'applied_outlet_id',
                'salary_expectation', 'preferred_job_scope', 'preferred_job_scope_other', 'preferred_work_environment',
                'preferred_work_environment_other', 'willing_out_of_town', 'willing_outside_java', 'willing_shift',
                'willing_overtime', 'is_smoker', 'has_computer_skill', 'wears_glasses', 'glasses_right_eye',
                'glasses_left_eye', 'join_reason', 'company_relation_note', 'career_goal', 'additional_information',
                'available_start_date', 'work_experiences', 'reference_contacts', 'organizations',
            ])) {
                return 4;
            }

            if ($this->matchesStep($key, [
                'medical_histories', 'weight_kg', 'height_cm', 'had_accident', 'accident_year', 'accident_type',
                'accident_effect', 'police_record', 'police_record_case', 'police_record_year', 'police_record_location',
                'psychology_test', 'psychology_test_year', 'psychology_test_location', 'psychology_test_purpose', 'skck_file',
            ])) {
                return 5;
            }

            if ($this->matchesStep($key, [
                'social_medias', 'honesty_statement', 'signature_data', 'graduation_diploma_file',
                'graduation_transcript_file', 'graduation_birth_certificate_file', 'supporting_files',
            ])) {
                return 6;
            }
        }

        return 1;
    }
    private function matchesStep(string $key, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($key === $prefix || str_starts_with($key, $prefix . '.')) {
                return true;
            }
        }

        return false;
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

    private function buildRequestLogContext(string $requestId): array
    {
        return [
            'request_id' => $requestId,
            'user_id' => $this->user()?->id,
            'candidate_id' => $this->resolveCandidate()?->id,
            'candidate_status' => $this->resolveCandidate()?->status,
            'route' => $this->route()?->getName(),
            'method' => $this->method(),
            'submit_mode' => $this->isFinalSubmit() ? 'final' : 'draft',
            'final_submit' => $this->isFinalSubmit(),
            'current_step' => $this->input('current_step'),
            'field_names' => array_values(array_diff(array_keys($this->except($this->fileInputNames())), ['signature_data'])),
            'ip' => $this->ip(),
            'user_agent' => Str::limit((string) $this->userAgent(), 500),
            'content_length' => $this->server('CONTENT_LENGTH'),
            'upload_limits' => \App\Support\ApplicationFormUploadLimit::describe(),
            'files' => $this->collectFileDiagnostics(),
            'temporary_tokens' => [
                'photo_ktp_file' => $this->safeTokenHash((string) $this->input('photo_ktp_file_token', '')),
                'scan_ktp_file' => $this->safeTokenHash((string) $this->input('scan_ktp_file_token', '')),
                'cv_file' => $this->safeTokenHash((string) $this->input('cv_file_token', '')),
            ],
        ];
    }

    private function safeTokenHash(string $token): ?string
    {
        $token = trim($token);

        return $token === '' ? null : substr(hash('sha256', $token), 0, 12);
    }

    private function collectFileDiagnostics(): array
    {
        $diagnostics = [];

        foreach ($this->fileInputNames() as $field) {
            $files = $field === 'supporting_files'
                ? array_values((array) $this->file('supporting_files', []))
                : [$this->file($field)];

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

    private function resolveCandidate(): ?Candidate
    {
        $user = $this->user();
        if (! $user) {
            return null;
        }

        return Candidate::query()
            ->when($user->id, fn ($query) => $query->where('user_id', $user->id))
            ->orWhere('email', trim((string) $user->email))
            ->latest('id')
            ->first();
    }
}



