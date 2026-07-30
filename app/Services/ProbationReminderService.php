<?php

namespace App\Services;

use App\Models\HrNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProbationReminderService
{
    /**
     * Generate reminders untuk karyawan yang probation_end_date-nya mendekat.
     * Default hari: 30,14,7,1.
     */
    public function generate(array $days = [30, 14, 7, 1]): int
    {
        $employeeColumns = $this->tableColumns('employees');
        $notificationColumns = $this->tableColumns('hr_notifications');

        if ($employeeColumns === [] || !in_array('probation_end_date', $employeeColumns, true)) {
            return 0;
        }

        $requiredNotificationColumns = ['type', 'title', 'unique_key'];
        foreach ($requiredNotificationColumns as $column) {
            if (!in_array($column, $notificationColumns, true)) {
                Log::warning('Probation reminder generation skipped because hr_notifications schema is incomplete', [
                    'missing_column' => $column,
                ]);
                return 0;
            }
        }

        $created = 0;
        $today   = now()->startOfDay();
        $select  = array_values(array_intersect(
            ['id', 'nik', 'full_name', 'probation_end_date', 'department_id', 'position_id', 'outlet_id'],
            $employeeColumns
        ));

        $hasUserIdCol = in_array('user_id', $notificationColumns, true);

        // Query HRD users once for the whole run
        $hrdUserIds = $hasUserIdCol
            ? User::whereIn('role', ['admin', 'finance'])->pluck('id')->all()
            : [];
        if ($hasUserIdCol && empty($hrdUserIds)) {
            $hrdUserIds = User::where('id', 1)->pluck('id')->all();
        }

        foreach ($days as $d) {
            $due = $today->copy()->addDays((int) $d)->toDateString();

            $employees = DB::table('employees')
                ->select($select === [] ? ['id'] : $select)
                ->whereNotNull('probation_end_date')
                ->whereDate('probation_end_date', '=', $due);

            if (in_array('deleted_at', $employeeColumns, true)) {
                $employees->whereNull('deleted_at');
            }

            $dayEmployees = $employees->get();
            if ($dayEmployees->isEmpty()) {
                continue;
            }

            // Build keys with new format (per employee + per HRD user) for batch existence check
            $dayKeys = [];
            foreach ($dayEmployees as $e) {
                $base = 'probation_reminder:emp:' . $e->id . ':due:' . $due . ':d' . $d;
                if ($hasUserIdCol && ! empty($hrdUserIds)) {
                    foreach ($hrdUserIds as $hrdId) {
                        $dayKeys[] = $base . '-u' . $hrdId;
                    }
                } else {
                    $dayKeys[] = $base;
                }
            }
            $existingKeys = HrNotification::query()
                ->whereIn('unique_key', $dayKeys)
                ->pluck('unique_key')
                ->flip();

            foreach ($dayEmployees as $e) {
                $base = 'probation_reminder:emp:' . $e->id . ':due:' . $due . ':d' . $d;

                $payload = [
                    'type'  => 'probation_reminder',
                    'title' => 'Reminder appraisal probation (H-' . $d . ')',
                ];
                if (in_array('body', $notificationColumns, true)) {
                    $payload['body'] = 'Karyawan ' . ($e->full_name ?? '-') . ' (NIK: ' . ($e->nik ?? '-') . ') akan berakhir probation pada ' . $due . '.';
                }
                if (in_array('due_date', $notificationColumns, true)) {
                    $payload['due_date'] = $due;
                }
                if (in_array('is_read', $notificationColumns, true)) {
                    $payload['is_read'] = false;
                }
                if (in_array('meta', $notificationColumns, true)) {
                    $payload['meta'] = [
                        'employee_id'        => $e->id,
                        'nik'                => $e->nik ?? null,
                        'full_name'          => $e->full_name ?? null,
                        'probation_end_date' => $due,
                        'days_before'        => (int) $d,
                        'department_id'      => $e->department_id ?? null,
                        'position_id'        => $e->position_id ?? null,
                        'outlet_id'          => $e->outlet_id ?? null,
                    ];
                }

                if ($hasUserIdCol && ! empty($hrdUserIds)) {
                    // Kirim ke setiap HRD/admin — tidak broadcast null
                    foreach ($hrdUserIds as $hrdId) {
                        $key = $base . '-u' . $hrdId;
                        if ($existingKeys->has($key)) {
                            continue;
                        }
                        HrNotification::query()->create(
                            array_merge($payload, ['unique_key' => $key, 'user_id' => $hrdId])
                        );
                        $created++;
                    }
                } else {
                    // Fallback: kolom user_id tidak ada di skema
                    if ($existingKeys->has($base)) {
                        continue;
                    }
                    HrNotification::query()->create(array_merge($payload, ['unique_key' => $base]));
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        return Schema::getColumnListing($table);
    }
}
