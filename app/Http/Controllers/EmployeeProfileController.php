<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeProfileChangeRequest;
use App\Models\HrNotification;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Services\EmployeeProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeProfileController extends Controller
{
    public function show(Request $request, EmployeeProfileService $service)
    {
        $user = $request->user(); abort_unless($user, 401); abort_unless($this->isEmployeeUser($user), 403);
        return view('employee-profile.show', ['snapshot' => $service->getEmployeeSnapshot($user), 'pendingRequest' => $this->latestRequest($user->id, ProfileChangeRequest::STATUS_PENDING), 'latestRejected' => $this->latestRequest($user->id, ProfileChangeRequest::STATUS_REJECTED), 'latestApproved' => $this->latestRequest($user->id, ProfileChangeRequest::STATUS_APPROVED)]);
    }

    public function edit(Request $request, EmployeeProfileService $service)
    {
        $user = $request->user(); abort_unless($user, 401); abort_unless($this->isEmployeeUser($user), 403);
        $snapshot = $service->getEmployeeSnapshot($user);
        return view('employee-profile.edit', ['snapshot' => $snapshot, 'formData' => $snapshot['editable_form'], 'pendingRequest' => $this->latestRequest($user->id, ProfileChangeRequest::STATUS_PENDING)]);
    }

    public function update(StoreEmployeeProfileChangeRequest $request, EmployeeProfileService $service): RedirectResponse
    {
        $user = $request->user(); abort_unless($user, 401); abort_unless($this->isEmployeeUser($user), 403);
        if (!Schema::hasTable('profile_change_requests')) return back()->withInput()->with('error', 'Modul pengajuan perubahan data belum siap di environment ini.');
        $pendingExists = ProfileChangeRequest::query()->where('user_id', $user->id)->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)->where('status', ProfileChangeRequest::STATUS_PENDING)->exists();
        if ($pendingExists) return back()->with('error', 'Masih ada pengajuan perubahan data yang menunggu verifikasi HRD.');

        $validated = $request->validated();
        $snapshot = $service->getEmployeeSnapshot($user);
        $editable = $snapshot['editable_form'];
        $personal = (array) ($editable['personal'] ?? []);
        $payroll = (array) ($editable['payroll'] ?? []);
        $graduationDocuments = (array) data_get($personal, 'graduation_documents', []);
        $input = fn(string $key, $fallback = null) => array_key_exists($key, $validated) ? $validated[$key] : $fallback;

        try {
            foreach ([['photo_ktp_file', 'photo_path', 'employee-profile/photos'], ['scan_ktp_file', 'ktp_path', 'employee-profile/ktp'], ['cv_file', 'cv_path', 'employee-profile/cv'], ['skck_file', 'skck_latest_path', 'employee-profile/skck']] as [$field, $target, $dir]) {
                if ($request->hasFile($field)) $personal[$target] = $this->storeUploadedFileSafely($request->file($field), $dir);
            }
            if (trim((string) $input('signature_data', '')) !== '') $personal['signature_path'] = $this->storeSignatureData((string) $input('signature_data', ''));
            foreach ([['graduation_diploma_file', 'diploma_path'], ['graduation_transcript_file', 'transcript_path'], ['graduation_birth_certificate_file', 'birth_certificate_path']] as [$field, $target]) {
                if ($request->hasFile($field)) $graduationDocuments[$target] = $this->storeUploadedFileSafely($request->file($field), 'employee-profile/graduation');
            }
            if ($request->hasFile('supporting_files')) {
                $graduationDocuments['supporting_files'] = [];
                foreach ((array) $request->file('supporting_files', []) as $file) if ($file instanceof UploadedFile) $graduationDocuments['supporting_files'][] = $this->storeUploadedFileSafely($file, 'employee-profile/supporting');
            }

            $payrollAttachments = [];
            foreach ([['sim_file', 'employee-profile/payroll/sim'], ['npwp_file', 'employee-profile/payroll/npwp'], ['bpjs_kes_file', 'employee-profile/payroll/bpjs-kes'], ['bpjs_tk_file', 'employee-profile/payroll/bpjs-tk'], ['passport_file', 'employee-profile/payroll/passport'], ['kk_file', 'employee-profile/payroll/kk']] as [$field, $dir]) {
                $payrollAttachments[$field] = $this->storeOptionalFile($request->file($field), $dir, $payroll[$field] ?? null);
            }

            $personalPayload = array_merge($personal, [
                'full_name' => $input('full_name', data_get($personal, 'full_name')),
                'ktp_number' => $input('ktp_number', data_get($personal, 'ktp_number')),
                'place_of_birth' => $input('place_of_birth', data_get($personal, 'place_of_birth')),
                'date_of_birth' => $input('date_of_birth', data_get($personal, 'date_of_birth')),
                'time_of_birth' => $input('time_of_birth', data_get($personal, 'time_of_birth')),
                'gender' => $input('gender', data_get($personal, 'gender')),
                'religion' => $input('religion', data_get($personal, 'religion')),
                'blood_type' => $input('blood_type', data_get($personal, 'blood_type')),
                'marital_status' => $input('marital_status', data_get($personal, 'marital_status')),
                'marriage_date' => $input('marriage_date', data_get($personal, 'marriage_date')),
                'whatsapp' => $input('whatsapp', data_get($personal, 'whatsapp')),
                'phone_number' => $input('phone_number', data_get($personal, 'phone_number')),
                'salary_expectation' => $input('salary_expectation', data_get($personal, 'salary_expectation')),
                'preferred_job_scope' => $input('preferred_job_scope', data_get($personal, 'preferred_job_scope')),
                'preferred_job_scope_other' => $input('preferred_job_scope_other', data_get($personal, 'preferred_job_scope_other')),
                'preferred_work_environment' => $input('preferred_work_environment', data_get($personal, 'preferred_work_environment')),
                'preferred_work_environment_other' => $input('preferred_work_environment_other', data_get($personal, 'preferred_work_environment_other')),
                'applied_position_name' => $input('applied_position_name', data_get($personal, 'applied_position_name')),
                'willing_out_of_town' => $input('willing_out_of_town', data_get($personal, 'willing_out_of_town')),
                'willing_outside_java' => $input('willing_outside_java', data_get($personal, 'willing_outside_java')),
                'willing_shift' => $input('willing_shift', data_get($personal, 'willing_shift')),
                'willing_overtime' => $input('willing_overtime', data_get($personal, 'willing_overtime')),
                'is_smoker' => $input('is_smoker', data_get($personal, 'is_smoker')),
                'has_computer_skill' => $input('has_computer_skill', data_get($personal, 'has_computer_skill')),
                'wears_glasses' => $input('wears_glasses', data_get($personal, 'wears_glasses')),
                'glasses_right_eye' => $input('glasses_right_eye', data_get($personal, 'glasses_right_eye')),
                'glasses_left_eye' => $input('glasses_left_eye', data_get($personal, 'glasses_left_eye')),
                'join_reason' => $input('join_reason', data_get($personal, 'join_reason')),
                'company_relation_note' => $input('company_relation_note', data_get($personal, 'company_relation_note')),
                'career_goal' => $input('career_goal', data_get($personal, 'career_goal')),
                'additional_information' => $input('additional_information', data_get($personal, 'additional_information')),
                'available_start_date' => $input('available_start_date', data_get($personal, 'available_start_date')),
                'honesty_statement' => $input('honesty_statement', data_get($personal, 'honesty_statement')),
                'emergency_contacts' => $input('emergency_contacts', data_get($personal, 'emergency_contacts', [])),
                'graduation_documents' => $graduationDocuments,
                'email' => $user->email,
            ]);

            $addressPayload = [
                'ktp_address' => $input('ktp_address', data_get($editable, 'address.ktp_address')),
                'ktp_province' => $input('ktp_province', data_get($editable, 'address.ktp_province')),
                'ktp_city' => $input('ktp_city', data_get($editable, 'address.ktp_city')),
                'ktp_rt' => $input('ktp_rt', data_get($editable, 'address.ktp_rt')),
                'ktp_rw' => $input('ktp_rw', data_get($editable, 'address.ktp_rw')),
                'ktp_kelurahan' => $input('ktp_kelurahan', data_get($editable, 'address.ktp_kelurahan')),
                'ktp_kecamatan' => $input('ktp_kecamatan', data_get($editable, 'address.ktp_kecamatan')),
                'domicile_address' => $input('domicile_address', data_get($editable, 'address.domicile_address')),
                'domicile_province' => $input('domicile_province', data_get($editable, 'address.domicile_province')),
                'domicile_city' => $input('domicile_city', data_get($editable, 'address.domicile_city')),
                'domicile_rt' => $input('domicile_rt', data_get($editable, 'address.domicile_rt')),
                'domicile_rw' => $input('domicile_rw', data_get($editable, 'address.domicile_rw')),
                'domicile_kelurahan' => $input('domicile_kelurahan', data_get($editable, 'address.domicile_kelurahan')),
                'domicile_kecamatan' => $input('domicile_kecamatan', data_get($editable, 'address.domicile_kecamatan')),
            ];

            $medicalPayload = [
                'histories' => $input('medical_histories', $editable['medical_histories'] ?? []),
                'weight_kg' => $input('weight_kg', data_get($editable, 'medical_meta.weight_kg')),
                'height_cm' => $input('height_cm', data_get($editable, 'medical_meta.height_cm')),
                'had_accident' => $input('had_accident', data_get($editable, 'medical_meta.had_accident')),
                'accident_year' => $input('accident_year', data_get($editable, 'medical_meta.accident_year')),
                'accident_type' => $input('accident_type', data_get($editable, 'medical_meta.accident_type')),
                'accident_effect' => $input('accident_effect', data_get($editable, 'medical_meta.accident_effect')),
                'police_record' => $input('police_record', data_get($editable, 'medical_meta.police_record')),
                'police_record_case' => $input('police_record_case', data_get($editable, 'medical_meta.police_record_case')),
                'police_record_year' => $input('police_record_year', data_get($editable, 'medical_meta.police_record_year')),
                'police_record_location' => $input('police_record_location', data_get($editable, 'medical_meta.police_record_location')),
                'psychology_test' => $input('psychology_test', data_get($editable, 'medical_meta.psychology_test')),
                'psychology_test_year' => $input('psychology_test_year', data_get($editable, 'medical_meta.psychology_test_year')),
                'psychology_test_location' => $input('psychology_test_location', data_get($editable, 'medical_meta.psychology_test_location')),
                'psychology_test_purpose' => $input('psychology_test_purpose', data_get($editable, 'medical_meta.psychology_test_purpose')),
            ];

            $changeRequest = $service->createChangeRequest($user, ['full_name' => (string) $input('full_name', ''), 'email_private' => (string) $input('email_private', ''), 'phone_number' => (string) $input('phone_number', '')], ['sim_number' => (string) $input('sim_number', ''), 'npwp_number' => (string) $input('npwp_number', ''), 'bpjs_kes_number' => (string) $input('bpjs_kes_number', ''), 'bpjs_tk_number' => (string) $input('bpjs_tk_number', ''), 'passport_number' => (string) $input('passport_number', ''), 'kk_number' => (string) $input('kk_number', '')], $payrollAttachments, [], ['personal' => $personalPayload, 'address' => $addressPayload, 'family' => $input('families', $editable['families'] ?? []), 'education' => $input('educations', $editable['educations'] ?? []), 'language' => $input('languages', $editable['languages'] ?? []), 'course' => $input('courses', $editable['courses'] ?? []), 'work' => $input('work_experiences', $editable['work_experiences'] ?? []), 'organization' => $input('organizations', $editable['organizations'] ?? []), 'reference_contacts' => $input('reference_contacts', $editable['reference_contacts'] ?? []), 'medical' => $medicalPayload, 'social' => $input('social_medias', $editable['social_medias'] ?? [])]);
            $this->notifyHrd($user, $changeRequest->id);
            return redirect()->route('employee-profile.show')->with('success', 'Pengajuan edit data lengkap berhasil dikirim ke HRD untuk diverifikasi.');
        } catch (\Throwable $e) {
            Log::error('Employee profile change request submit failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Terjadi kendala saat mengajukan perubahan data. Silakan coba lagi.');
        }
    }

    private function latestRequest(int $userId, string $status): ?ProfileChangeRequest
    {
        if (!Schema::hasTable('profile_change_requests')) return null;
        return ProfileChangeRequest::query()->where('user_id', $userId)->where('entity_type', ProfileChangeRequest::ENTITY_EMPLOYEE_PROFILE)->where('status', $status)->latest('id')->first();
    }

    private function notifyHrd(User $user, int $changeRequestId): void
    {
        if (!Schema::hasTable('hr_notifications')) return;
        foreach (User::query()->whereIn('role', ['admin', 'hrd'])->pluck('id') as $hrUserId) {
            HrNotification::query()->create(['user_id' => (int) $hrUserId, 'type' => 'profile_change_request', 'title' => 'Pengajuan Edit Profil Karyawan', 'body' => 'Ada perubahan data lengkap karyawan atau probation yang perlu diverifikasi HRD.', 'due_date' => now()->toDateString(), 'is_read' => false, 'unique_key' => 'employee-profile-change-' . $changeRequestId . '-' . $hrUserId . '-' . now()->timestamp, 'meta' => ['change_request_id' => $changeRequestId, 'route' => route('hrd.probation-verifications.show', $changeRequestId), 'submitter_user_id' => $user->id]]);
        }
    }

    private function isEmployeeUser(User $user): bool
    {
        if ((int) ($user->employee_id ?? 0) <= 0) return false;
        if (in_array((string) ($user->role ?? null), ['employee', 'probation'], true)) return true;
        if (Schema::hasColumn('users', 'employee_status') && in_array((string) $user->employee_status, ['probation', 'employee'], true)) return true;
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'status_employment')) return false;
        return DB::table('employees')->where('id', (int) $user->employee_id)->whereIn('status_employment', ['probation', 'contract', 'permanent'])->exists();
    }

    private function storeOptionalFile(?UploadedFile $file, string $directory, ?string $existingPath = null): ?string
    {
        return $file ? $this->storeUploadedFileSafely($file, $directory) : ($existingPath ? (string) $existingPath : null);
    }

    private function storeUploadedFileSafely(?UploadedFile $file, string $directory): string
    {
        if ($file === null) throw new \InvalidArgumentException('Uploaded file is required.');
        if (!$file->isValid()) throw new \RuntimeException('Upload file gagal.');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        return (string) $file->storeAs($directory, Str::uuid()->toString() . ($extension !== '' ? ".{$extension}" : ''), 'public');
    }

    private function storeSignatureData(string $signatureData): string
    {
        if (!preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $signatureData)) throw new \RuntimeException('Tanda tangan digital tidak valid.');
        [$meta, $encoded] = explode(',', $signatureData, 2); $binary = base64_decode($encoded, true); if ($binary === false) throw new \RuntimeException('Tanda tangan digital gagal dibaca.');
        $extension = str_contains($meta, 'jpeg') || str_contains($meta, 'jpg') ? 'jpg' : 'png'; $path = 'employee-profile/signatures/' . Str::uuid()->toString() . '.' . $extension; Storage::disk('public')->put($path, $binary); return $path;
    }
}
