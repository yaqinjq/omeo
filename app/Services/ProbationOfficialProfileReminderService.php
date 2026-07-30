<?php

namespace App\Services;

use App\Models\HrNotification;
use App\Models\User;

class ProbationOfficialProfileReminderService
{
    public function __construct(private readonly EmployeeProfileCompletenessService $completenessService)
    {
    }

    public function generate(): int
    {
        $items = $this->completenessService->listEmployeesNeedsNokomSetup(200);
        if (empty($items)) {
            return 0;
        }

        $recipientIds = User::query()
            ->whereIn('role', ['admin', 'hrd'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($recipientIds)) {
            return 0;
        }

        // Build all candidate keys upfront, then batch-check existence in ONE query
        $candidateKeys = [];
        foreach ($items as $item) {
            foreach ($recipientIds as $recipientId) {
                $candidateKeys[] = 'probation_official_profile:emp:' . (int) $item->employee_id . ':hr:' . $recipientId;
            }
        }

        $existingKeys = HrNotification::query()
            ->whereIn('unique_key', $candidateKeys)
            ->pluck('unique_key')
            ->flip();

        $created = 0;

        foreach ($items as $item) {
            foreach ($recipientIds as $recipientId) {
                $uniqueKey = 'probation_official_profile:emp:' . (int) $item->employee_id . ':hr:' . $recipientId;
                if ($existingKeys->has($uniqueKey)) {
                    continue;
                }

                HrNotification::query()->create([
                    'user_id' => $recipientId,
                    'type' => 'probation_official_profile',
                    'title' => 'Karyawan probation siap dibuatkan NOKOM',
                    'body' => 'Payroll sudah lengkap & terverifikasi. Segera isi NOKOM, Join Date, dan Jabatan/Posisi.',
                    'due_date' => now()->toDateString(),
                    'is_read' => false,
                    'unique_key' => $uniqueKey,
                    'meta' => [
                        'employee_id' => (int) $item->employee_id,
                        'employee_nik' => (string) ($item->nik ?? '-'),
                        'employee_name' => (string) ($item->full_name ?? '-'),
                        'employee_email' => (string) ($item->email ?? '-'),
                        'route' => $item->edit_url,
                    ],
                ]);

                $created++;
            }
        }

        return $created;
    }
}

