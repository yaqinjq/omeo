<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ApplicantProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $profile = ApplicantProfile::query()->where('user_id', $this->user()?->id)->first();
        $hasPhoto = trim((string) ($profile?->photo_path ?? '')) !== '';
        $hasKtp = trim((string) ($profile?->ktp_path ?? '')) !== '';
        $hasCv = trim((string) ($profile?->cv_path ?? '')) !== '';
        $hasSignature = trim((string) data_get($profile?->personal_json, 'signature_path', '')) !== '';
        $imageOrPdfRules = ['file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'];

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email_private' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'ktp_number' => ['required', 'string', 'max:50'],
            'place_of_birth' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date'],
            'time_of_birth' => ['nullable', 'date_format:H:i'],
            'gender' => ['required', 'string', 'max:50'],
            'religion' => ['required', 'string', 'max:50'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'marital_status' => ['required', 'string', 'max:50'],
            'marriage_date' => ['nullable', 'date'],
            'whatsapp' => ['required', 'string', 'max:20'],

            'salary_expectation' => ['nullable', 'numeric', 'min:0'],
            'preferred_job_scope' => ['nullable', Rule::in(['Managerial', 'Tekhnikal', 'Klerikal', 'Lainnya'])],
            'preferred_job_scope_other' => ['nullable', 'string', 'max:255'],
            'preferred_work_environment' => ['nullable', Rule::in(['Kantor', 'Luar Kantor', 'Pabrik', 'Laboratorium', 'Mall', 'Lainnya'])],
            'preferred_work_environment_other' => ['nullable', 'string', 'max:255'],
            'applied_position_name' => ['nullable', 'string', 'max:255'],
            'willing_out_of_town' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'willing_outside_java' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'willing_shift' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'willing_overtime' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'is_smoker' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'has_computer_skill' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'wears_glasses' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'glasses_right_eye' => ['nullable', 'string', 'max:50'],
            'glasses_left_eye' => ['nullable', 'string', 'max:50'],
            'join_reason' => ['nullable', 'string', 'max:4000'],
            'company_relation_note' => ['nullable', 'string', 'max:4000'],
            'career_goal' => ['nullable', 'string', 'max:4000'],
            'additional_information' => ['nullable', 'string', 'max:4000'],
            'available_start_date' => ['nullable', 'date'],
            'honesty_statement' => ['nullable', 'string', 'max:5000'],

            'photo_ktp_file' => array_merge([$hasPhoto ? 'nullable' : 'required'], $imageOrPdfRules),
            'scan_ktp_file' => array_merge([$hasKtp ? 'nullable' : 'required'], $imageOrPdfRules),
            'cv_file' => ['nullable', Rule::requiredIf(! $hasCv), 'file', 'max:5120', 'mimes:pdf'],
            'signature_data' => [$hasSignature ? 'nullable' : 'nullable', 'string'],
            'skck_file' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'],
            'graduation_diploma_file' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'],
            'graduation_transcript_file' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'],
            'graduation_birth_certificate_file' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'],
            'supporting_files' => ['nullable', 'array', 'max:5'],
            'supporting_files.*' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,pdf'],

            'ktp_address' => ['required', 'string'],
            'ktp_province' => ['nullable', 'string', 'max:100'],
            'ktp_city' => ['nullable', 'string', 'max:100'],
            'ktp_rt' => ['nullable', 'string', 'max:10'],
            'ktp_rw' => ['nullable', 'string', 'max:10'],
            'ktp_kelurahan' => ['nullable', 'string', 'max:100'],
            'ktp_kecamatan' => ['nullable', 'string', 'max:100'],
            'domicile_address' => ['required', 'string'],
            'domicile_province' => ['nullable', 'string', 'max:100'],
            'domicile_city' => ['nullable', 'string', 'max:100'],
            'domicile_rt' => ['nullable', 'string', 'max:10'],
            'domicile_rw' => ['nullable', 'string', 'max:10'],
            'domicile_kelurahan' => ['nullable', 'string', 'max:100'],
            'domicile_kecamatan' => ['nullable', 'string', 'max:100'],

            'families' => ['required', 'array', 'min:1'],
            'families.*.relation' => ['required', 'string', 'max:100'],
            'families.*.name' => ['required', 'string', 'max:255'],
            'families.*.gender' => ['required', 'string', 'max:50'],
            'families.*.dob' => ['nullable', 'date'],
            'families.*.education' => ['nullable', 'string', 'max:100'],
            'families.*.job' => ['nullable', 'string', 'max:255'],
            'families.*.status_note' => ['nullable', 'string', 'max:100'],

            'emergency_contacts' => ['nullable', 'array'],
            'emergency_contacts.*.name' => ['required_with:emergency_contacts.*.relation,emergency_contacts.*.phone,emergency_contacts.*.address', 'string', 'max:255'],
            'emergency_contacts.*.relation' => ['nullable', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['nullable', 'string', 'max:25'],
            'emergency_contacts.*.address' => ['nullable', 'string', 'max:500'],

            'educations' => ['required', 'array', 'min:1'],
            'educations.*.level' => ['required', 'string', 'max:50'],
            'educations.*.school' => ['required', 'string', 'max:255'],
            'educations.*.major' => ['required', 'string', 'max:255'],
            'educations.*.year_in' => ['required', 'numeric'],
            'educations.*.year_out' => ['required', 'numeric'],
            'educations.*.gpa' => ['nullable', 'string', 'max:20'],

            'languages' => ['nullable', 'array'],
            'courses' => ['nullable', 'array'],
            'work_experiences' => ['required', 'array', 'min:1'],
            'work_experiences.*.company' => ['required', 'string', 'max:255'],
            'work_experiences.*.position' => ['required', 'string', 'max:255'],
            'work_experiences.*.date_start' => ['required', 'date'],
            'work_experiences.*.date_end' => ['nullable', 'date'],
            'work_experiences.*.salary' => ['required', 'string', 'max:100'],
            'work_experiences.*.reason' => ['required', 'string', 'max:255'],
            'reference_contacts' => ['required', 'array', 'min:1'],
            'reference_contacts.*.name' => ['required', 'string', 'max:255'],
            'reference_contacts.*.relation' => ['required', 'string', 'max:100'],
            'reference_contacts.*.company' => ['required', 'string', 'max:255'],
            'reference_contacts.*.phone' => ['required', 'string', 'max:25'],
            'organizations' => ['nullable', 'array'],

            'medical_histories' => ['required', 'array', 'min:1'],
            'medical_histories.*.illness' => ['required', 'string', 'max:255'],
            'medical_histories.*.year' => ['required', 'numeric'],
            'medical_histories.*.hospitalized' => ['required', 'string', 'max:20'],
            'medical_histories.*.note' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['nullable', 'numeric', 'min:1'],
            'height_cm' => ['nullable', 'numeric', 'min:1'],
            'had_accident' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'accident_year' => ['nullable', 'numeric'],
            'accident_type' => ['nullable', 'string', 'max:255'],
            'accident_effect' => ['nullable', 'string', 'max:255'],
            'police_record' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'police_record_case' => ['nullable', 'string', 'max:255'],
            'police_record_year' => ['nullable', 'numeric'],
            'police_record_location' => ['nullable', 'string', 'max:255'],
            'psychology_test' => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'psychology_test_year' => ['nullable', 'numeric'],
            'psychology_test_location' => ['nullable', 'string', 'max:255'],
            'psychology_test_purpose' => ['nullable', 'string', 'max:255'],
            'social_medias' => ['nullable', 'array'],

            'sim_number' => ['nullable', 'string', 'max:100'],
            'npwp_number' => ['nullable', 'string', 'max:100'],
            'bpjs_kes_number' => ['nullable', 'string', 'max:100'],
            'bpjs_tk_number' => ['nullable', 'string', 'max:100'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'kk_number' => ['nullable', 'string', 'max:100'],
            'sim_file' => ['nullable', 'file', 'max:5120'],
            'npwp_file' => ['nullable', 'file', 'max:5120'],
            'bpjs_kes_file' => ['nullable', 'file', 'max:5120'],
            'bpjs_tk_file' => ['nullable', 'file', 'max:5120'],
            'passport_file' => ['nullable', 'file', 'max:5120'],
            'kk_file' => ['nullable', 'file', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'families' => $this->filterRows($this->input('families', [])),
            'emergency_contacts' => $this->filterRows($this->input('emergency_contacts', [])),
            'educations' => $this->filterRows($this->input('educations', [])),
            'languages' => $this->filterRows($this->input('languages', [])),
            'courses' => $this->filterRows($this->input('courses', [])),
            'work_experiences' => $this->filterRows($this->input('work_experiences', [])),
            'reference_contacts' => $this->filterRows($this->input('reference_contacts', [])),
            'organizations' => $this->filterRows($this->input('organizations', [])),
            'medical_histories' => $this->filterRows($this->input('medical_histories', [])),
            'social_medias' => $this->filterRows($this->input('social_medias', [])),
            'signature_data' => trim((string) $this->input('signature_data', '')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('preferred_job_scope') === 'Lainnya' && trim((string) $this->input('preferred_job_scope_other')) === '') {
                $validator->errors()->add('preferred_job_scope_other', 'Ruang lingkup pekerjaan lainnya wajib dijelaskan.');
            }
            if ($this->input('preferred_work_environment') === 'Lainnya' && trim((string) $this->input('preferred_work_environment_other')) === '') {
                $validator->errors()->add('preferred_work_environment_other', 'Lingkungan kerja lainnya wajib dijelaskan.');
            }
        });
    }

    private function filterRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
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
                $out[] = $trimmed;
            }
        }

        return array_values($out);
    }
}
