<?php

namespace App\Services;

use App\Models\HrNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppraisalProbationReminderService
{
    public function generate(array $days = [14, 7, 3, 1]): int
    {
        if (!Schema::hasTable('employees') || !Schema::hasTable('hr_notifications')) {
            return 0;
        }

        $employeeColumns = Schema::getColumnListing('employees');
        $notificationColumns = Schema::getColumnListing('hr_notifications');
        if (!in_array('probation_end_date', $employeeColumns, true)) {
            return 0;
        }

        $recipientIds = User::query()->whereIn('role', ['admin', 'hrd'])->pluck('id');
        if ($recipientIds->isEmpty()) {
            return 0;
        }

        $created = 0;
        foreach ($days as $day) {
            $targetDate = now()->startOfDay()->addDays((int) $day)->toDateString();
            $employees = DB::table('employees')
                ->select(['id', 'nik', 'full_name', 'probation_end_date'])
                ->whereNotNull('probation_end_date')
                ->whereDate('probation_end_date', $targetDate)
                ->get();

            foreach ($employees as $employee) {
                foreach ($recipientIds as $recipientId) {
                    $uniqueKey = 'appraisal-probation-reminder:' . $employee->id . ':' . $recipientId . ':' . $targetDate . ':' . $day;
                    if (HrNotification::query()->where('unique_key', $uniqueKey)->exists()) {
                        continue;
                    }

                    $payload = [
                        'user_id' => (int) $recipientId,
                        'type' => 'appraisal_probation_reminder',
                        'title' => 'Reminder invitation appraisal probation (H-' . $day . ')',
                        'body' => 'Probation ' . ($employee->full_name ?? '-') . ' akan berakhir pada ' . $targetDate . '. Tetapkan evaluator appraisal yang berhak.',
                        'due_date' => $targetDate,
                        'is_read' => false,
                        'unique_key' => $uniqueKey,
                        'meta' => [
                            'employee_id' => $employee->id,
                            'nik' => $employee->nik ?? null,
                            'probation_end_date' => $targetDate,
                            'days_before' => (int) $day,
                            'route' => route('appraisals.assignment', ['days' => max(14, (int) $day)]),
                        ],
                    ];

                    $sanitized = [];
                    foreach ($payload as $key => $value) {
                        if (in_array($key, $notificationColumns, true)) {
                            $sanitized[$key] = $value;
                        }
                    }

                    HrNotification::query()->create($sanitized);
                    $created++;
                }
            }
        }

        return $created;
    }
}
