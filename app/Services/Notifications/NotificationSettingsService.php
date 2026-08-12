<?php

namespace App\Services\Notifications;

use App\Models\AppSetting;

class NotificationSettingsService
{
    public function resolve(?AppSetting $setting = null): array
    {
        $setting ??= AppSetting::query()->first();

        $eventPreferences = $this->mergeEventPreferences((array) ($setting?->notification_event_preferences_json ?? []));
        $templates = $this->mergeTemplates((array) ($setting?->notification_templates_json ?? []));

        return [
            'events' => $eventPreferences,
            'templates' => $templates,
            'whatsapp' => [
                'provider' => trim((string) ($setting?->notification_whatsapp_provider ?? 'official_api')) ?: 'official_api',
                'api_version' => trim((string) ($setting?->notification_whatsapp_api_version ?? 'v23.0')) ?: 'v23.0',
                'business_account_id' => trim((string) ($setting?->notification_whatsapp_business_account_id ?? '')),
                'phone_number_id' => trim((string) ($setting?->notification_whatsapp_phone_number_id ?? '')),
                'access_token' => trim((string) ($setting?->notification_whatsapp_access_token ?? '')),
                'default_country_code' => trim((string) ($setting?->notification_whatsapp_default_country_code ?? '62')) ?: '62',
            ],
        ];
    }

    public function defaults(): array
    {
        return [
            'events' => $this->defaultEventPreferences(),
            'templates' => $this->defaultTemplates(),
            'whatsapp' => [
                'provider' => 'official_api',
                'api_version' => 'v23.0',
                'business_account_id' => '',
                'phone_number_id' => '',
                'access_token' => '',
                'default_country_code' => '62',
            ],
        ];
    }

    public function settingFormLabels(): array
    {
        return [
            'candidate_status_shortlisted' => 'Peserta lolos administrasi / shortlist',
            'candidate_status_accepted' => 'Peserta diterima / lanjut onboarding',
            'employee_profile_change_reviewed' => 'Karyawan menerima hasil review perubahan data',
            'appraisal_invitation' => 'Appraisal: evaluator ditunjuk',
            'appraisal_reminder' => 'Appraisal: reminder pengisian penilaian',
            'appraisal_edit_request' => 'Appraisal: evaluator ajukan izin edit',
            'appraisal_edit_approved' => 'Appraisal: izin edit disetujui HRD',
            'appraisal_edit_rejected' => 'Appraisal: izin edit ditolak HRD',
            'appraisal_due_date_extended' => 'Appraisal: due date diperpanjang',
        ];
    }

    /**
     * Placeholder {variabel} yang benar-benar terisi untuk tiap event, supaya
     * HRD tidak menebak-nebak di halaman pengaturan template. "name" selalu
     * ada di semua event (nama penerima), tidak perlu diulang di sini.
     */
    public function templateVariableHints(): array
    {
        return [
            'candidate_status_shortlisted' => ['status_label'],
            'candidate_status_accepted' => ['status_label'],
            'employee_profile_change_reviewed' => ['message'],
            'appraisal_invitation' => ['employee_name', 'due_date'],
            'appraisal_reminder' => ['employee_name', 'due_date'],
            'appraisal_edit_request' => ['evaluator_name', 'employee_name', 'reason'],
            'appraisal_edit_approved' => ['employee_name', 'due_date'],
            'appraisal_edit_rejected' => ['employee_name', 'review_note'],
            'appraisal_due_date_extended' => ['employee_name', 'old_due_date', 'new_due_date'],
        ];
    }

    private function defaultEventPreferences(): array
    {
        return [
            'candidate_status_shortlisted' => [
                'internal' => false,
                'email' => true,
                'whatsapp' => false,
            ],
            'candidate_status_accepted' => [
                'internal' => false,
                'email' => true,
                'whatsapp' => false,
            ],
            'employee_profile_change_reviewed' => [
                'internal' => true,
                'email' => true,
                'whatsapp' => true,
            ],
            'appraisal_invitation' => [
                'internal' => true,
                'email' => true,
                'whatsapp' => false,
            ],
            'appraisal_reminder' => [
                'internal' => true,
                'email' => true,
                'whatsapp' => false,
            ],
            'appraisal_edit_request' => [
                'internal' => true,
                'email' => true,
                'whatsapp' => false,
            ],
            'appraisal_edit_approved' => [
                'internal' => true,
                'email' => true,
                'whatsapp' => false,
            ],
            'appraisal_edit_rejected' => [
                'internal' => true,
                'email' => true,
                'whatsapp' => false,
            ],
            'appraisal_due_date_extended' => [
                'internal' => true,
                'email' => true,
                'whatsapp' => false,
            ],
        ];
    }

    private function defaultTemplates(): array
    {
        return [
            'candidate_status_shortlisted' => [
                'title' => 'Status lamaran Anda diperbarui',
                'body' => 'Halo {name}, status lamaran Anda di OMEO sekarang adalah {status_label}. Silakan cek portal kandidat untuk langkah berikutnya.',
            ],
            'candidate_status_accepted' => [
                'title' => 'Selamat, Anda diterima',
                'body' => 'Halo {name}, selamat. Status lamaran Anda di OMEO sekarang adalah {status_label}. Tim kami akan menghubungi Anda untuk proses berikutnya.',
            ],
            'employee_profile_change_reviewed' => [
                'title' => 'Update pengajuan data karyawan',
                'body' => 'Halo {name}, {message} Buka aplikasi OMEO untuk melihat detail terbaru.',
            ],
            'appraisal_invitation' => [
                'title' => 'Anda ditunjuk sebagai evaluator appraisal',
                'body' => "Halo {name},\n\nAnda ditunjuk sebagai evaluator appraisal untuk {employee_name}. Mohon isi penilaian sebelum {due_date}.\n\nBuka aplikasi OMEO untuk mulai mengisi.",
            ],
            'appraisal_reminder' => [
                'title' => 'Reminder pengisian appraisal',
                'body' => "Halo {name},\n\nMohon segera lengkapi appraisal untuk {employee_name} (due date {due_date}) agar review probation tidak tertunda.\n\nBuka aplikasi OMEO untuk mengisi.",
            ],
            'appraisal_edit_request' => [
                'title' => 'Permintaan edit penilaian appraisal',
                'body' => "Halo {name},\n\n{evaluator_name} minta izin edit ulang penilaian untuk {employee_name}.\nAlasan: {reason}\n\nBuka aplikasi OMEO untuk menyetujui/menolak.",
            ],
            'appraisal_edit_approved' => [
                'title' => 'Permintaan edit penilaian disetujui',
                'body' => "Halo {name},\n\nHRD menyetujui permintaan edit Anda untuk penilaian {employee_name}. Segera edit sebelum due date ({due_date}).",
            ],
            'appraisal_edit_rejected' => [
                'title' => 'Permintaan edit penilaian ditolak',
                'body' => "Halo {name},\n\nHRD menolak permintaan edit Anda untuk penilaian {employee_name}.\nCatatan: {review_note}",
            ],
            'appraisal_due_date_extended' => [
                'title' => 'Due date appraisal diperpanjang',
                'body' => "Halo {name},\n\nDue date appraisal untuk {employee_name} diperpanjang dari {old_due_date} ke {new_due_date}.",
            ],
        ];
    }

    private function mergeEventPreferences(array $stored): array
    {
        $defaults = $this->defaultEventPreferences();

        foreach ($defaults as $eventKey => $channels) {
            $current = is_array($stored[$eventKey] ?? null) ? $stored[$eventKey] : [];
            $defaults[$eventKey] = [
                'internal' => (bool) ($current['internal'] ?? $channels['internal']),
                'email' => (bool) ($current['email'] ?? $channels['email']),
                'whatsapp' => (bool) ($current['whatsapp'] ?? $channels['whatsapp']),
            ];
        }

        return $defaults;
    }

    private function mergeTemplates(array $stored): array
    {
        $defaults = $this->defaultTemplates();

        foreach ($defaults as $eventKey => $template) {
            $current = is_array($stored[$eventKey] ?? null) ? $stored[$eventKey] : [];
            $defaults[$eventKey] = [
                'title' => trim((string) ($current['title'] ?? $template['title'])) ?: $template['title'],
                'body' => trim((string) ($current['body'] ?? $template['body'])) ?: $template['body'],
                'attachment_path' => trim((string) ($current['attachment_path'] ?? '')) ?: null,
                'attachment_name' => trim((string) ($current['attachment_name'] ?? '')) ?: null,
            ];
        }

        return $defaults;
    }
}
