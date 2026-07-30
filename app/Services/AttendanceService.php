<?php

namespace App\Services;

use App\Models\AttendanceLeave;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSession;
use App\Models\Outlet;
use App\Models\User;
use Carbon\Carbon;

class AttendanceService
{
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function outletTimezone(?Outlet $outlet): string
    {
        return (string) ($outlet?->timezone ?: 'Asia/Jakarta');
    }

    public function nowUtc(): Carbon
    {
        return now('UTC');
    }

    public function localDate(Carbon $utcTime, string $timezone): string
    {
        return $utcTime->copy()->setTimezone($timezone)->toDateString();
    }

    public function effectiveRadiusMeters(Outlet $outlet): int
    {
        $configured = $outlet->radius_meters ?? $outlet->geofence_radius_m ?? 5;
        $configured = (int) $configured;

        return max(1, $configured);
    }

    public function maxAcceptedAccuracyMeters(?Outlet $outlet = null): int
    {
        $baseline = 50;

        if (! $outlet) {
            return $baseline;
        }

        return max($baseline, $this->effectiveRadiusMeters($outlet));
    }

    /**
     * @return array{shift_code:?string,scheduled_in_local:?string,scheduled_out_local:?string,outlet_mismatch:bool,schedule_mismatch:bool}
     */
    public function resolveSchedule(User $user, Outlet $outlet, string $workDate): array
    {
        $schedule = AttendanceSchedule::query()
            ->with('shift:id,code,in_time,out_time')
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if (! $schedule) {
            return [
                'shift_code' => 'REG',
                'scheduled_in_local' => $outlet->work_start_time,
                'scheduled_out_local' => $outlet->work_end_time,
                'outlet_mismatch' => false,
                'schedule_mismatch' => false,
            ];
        }

        $scheduleIn = $schedule->shift?->in_time ?: $outlet->work_start_time;
        $scheduleOut = $schedule->shift?->out_time ?: $outlet->work_end_time;

        return [
            'shift_code' => $schedule->shift_code ?: $schedule->shift?->code,
            'scheduled_in_local' => $scheduleIn,
            'scheduled_out_local' => $scheduleOut,
            'outlet_mismatch' => $schedule->outlet_id !== null && (int) $schedule->outlet_id !== (int) $outlet->id,
            'schedule_mismatch' => $schedule->master_shift_id !== null && $schedule->shift === null,
        ];
    }

    public function isOnApprovedLeave(int $userId, string $workDate): bool
    {
        return AttendanceLeave::query()
            ->where('user_id', $userId)
            ->whereNotNull('approved_at')
            ->whereDate('date_from', '<=', $workDate)
            ->whereDate('date_to', '>=', $workDate)
            ->exists();
    }

    public function recomputeSession(AttendanceSession $session, string $timezone): AttendanceSession
    {
        $firstInUtc = $session->first_in_at_utc ? Carbon::parse($session->first_in_at_utc, 'UTC') : null;
        $lastOutUtc = $session->last_out_at_utc ? Carbon::parse($session->last_out_at_utc, 'UTC') : null;

        $workMinutes = 0;
        if ($firstInUtc && $lastOutUtc && $lastOutUtc->greaterThan($firstInUtc)) {
            $workMinutes = $firstInUtc->diffInMinutes($lastOutUtc);
        }

        $lateMinutes = 0;
        if ($firstInUtc && $session->scheduled_in_local) {
            $firstInLocal = $firstInUtc->copy()->setTimezone($timezone);
            $scheduledIn = Carbon::parse($firstInLocal->toDateString() . ' ' . $session->scheduled_in_local, $timezone);
            $lateMinutes = max(0, $scheduledIn->diffInMinutes($firstInLocal, false));
        }

        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;
        if ($lastOutUtc && $session->scheduled_out_local) {
            $lastOutLocal = $lastOutUtc->copy()->setTimezone($timezone);
            $scheduledOut = Carbon::parse($lastOutLocal->toDateString() . ' ' . $session->scheduled_out_local, $timezone);
            $delta = $scheduledOut->diffInMinutes($lastOutLocal, false);
            $earlyLeaveMinutes = max(0, -1 * $delta);
            $overtimeMinutes = max(0, $delta);
        }

        $status = $session->status;
        if ($status !== 'leave' && $status !== 'off' && $status !== 'schedule_mismatch' && $status !== 'outlet_mismatch') {
            if ($firstInUtc && $lastOutUtc) {
                $status = 'present';
            } elseif ($firstInUtc || $lastOutUtc) {
                $status = 'incomplete';
            } else {
                $status = 'absent';
            }
        }

        $scanData = $session->scans()
            ->orderBy('scanned_at_utc')
            ->get()
            ->map(function ($scan) use ($timezone) {
                $local = $scan->scanned_at_utc
                    ? Carbon::parse($scan->scanned_at_utc, 'UTC')->setTimezone($timezone)
                    : null;

                return $local?->format('H:i');
            })
            ->filter()
            ->values()
            ->all();

        $summary = (array) ($session->summary_json ?? []);
        $summary['scan_data'] = implode(',', $scanData);

        $session->fill([
            'total_work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $status,
            'summary_json' => $summary,
        ]);

        return $session;
    }
}