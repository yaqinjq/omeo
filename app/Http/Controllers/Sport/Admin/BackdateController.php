<?php

namespace App\Http\Controllers\Sport\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sport\Attendance;
use App\Models\Sport\BackdateAudit;
use App\Models\Sport\BackdateSetting;
use App\Models\Sport\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Backdate attendance MKO — HANYA Admin Finance (Spesifikasi v1.1 Section
 * 6, 9, 13). Fitur ini NONAKTIF TOTAL (ditolak bahkan untuk Admin Finance)
 * selama sport_backdate_settings.max_backdate_days masih NULL — nilai ini
 * TIDAK diisi default oleh migration, harus diisi manual dulu di sini.
 * Jangan menambahkan nilai default/fallback untuk batas hari mundur; itu
 * keputusan owner yang belum ada (lihat Open Decision #5).
 */
class BackdateController extends Controller
{
    public function editSettings(): View
    {
        $setting = BackdateSetting::current();
        $audits = BackdateAudit::with(['event', 'employee', 'actor'])->latest('performed_at')->limit(20)->get();

        return view('sport.admin.backdate.settings', ['setting' => $setting, 'audits' => $audits]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_backdate_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $setting = BackdateSetting::query()->first();
        if ($setting) {
            $setting->update([
                'max_backdate_days' => $data['max_backdate_days'] ?? null,
                'updated_by_user_id' => $request->user()->id,
            ]);
        } else {
            BackdateSetting::create([
                'max_backdate_days' => $data['max_backdate_days'] ?? null,
                'updated_by_user_id' => $request->user()->id,
            ]);
        }

        $message = ($data['max_backdate_days'] ?? null)
            ? "Batas backdate diset {$data['max_backdate_days']} hari. Fitur backdate aktif."
            : 'Batas backdate dikosongkan. Fitur backdate kembali NONAKTIF untuk semua role, termasuk Admin Finance.';

        return back()->with('success', $message);
    }

    public function store(Request $request, Registration $registration): RedirectResponse
    {
        $setting = BackdateSetting::current();

        if (! $setting->isEnabled()) {
            return back()->with('error', 'Fitur backdate belum diaktifkan. Set batas maksimum hari mundur terlebih dahulu di halaman Setting Backdate.');
        }

        $data = $request->validate([
            'confirmed_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $event = $registration->event;
        $newConfirmedAt = Carbon::parse($data['confirmed_at']);
        $now = Carbon::now();

        if ($now->lt($event->end_at)) {
            return back()->with('error', 'Event ini belum selesai — backdate hanya untuk event yang sudah berakhir.');
        }

        $daysSinceEventEnd = $event->end_at->diffInDays($now);
        if ($daysSinceEventEnd > $setting->max_backdate_days) {
            return back()->with('error', "Event ini sudah lebih dari {$setting->max_backdate_days} hari yang lalu — di luar batas backdate yang diizinkan.");
        }

        DB::transaction(function () use ($registration, $event, $newConfirmedAt, $data, $request) {
            $existing = Attendance::where('sport_registration_id', $registration->id)->first();
            $before = $existing?->confirmed_at;

            if ($existing) {
                $existing->update([
                    'confirmed_at' => $newConfirmedAt,
                    'is_backdated' => true,
                    'backdated_by_user_id' => $request->user()->id,
                    'backdate_reason' => $data['reason'],
                ]);
                $attendance = $existing;
                $action = BackdateAudit::ACTION_UPDATE;
            } else {
                $attendance = Attendance::create([
                    'sport_event_id' => $event->id,
                    'sport_registration_id' => $registration->id,
                    'employee_id' => $registration->employee_id,
                    'confirmed_at' => $newConfirmedAt,
                    'is_backdated' => true,
                    'backdated_by_user_id' => $request->user()->id,
                    'backdate_reason' => $data['reason'],
                ]);
                $action = BackdateAudit::ACTION_CREATE;
            }

            BackdateAudit::create([
                'sport_attendance_id' => $attendance->id,
                'sport_event_id' => $event->id,
                'employee_id' => $registration->employee_id,
                'action' => $action,
                'reason' => $data['reason'],
                'before_confirmed_at' => $before,
                'after_confirmed_at' => $newConfirmedAt,
                'actor_user_id' => $request->user()->id,
                'performed_at' => now(),
            ]);
        });

        return back()->with('success', 'Kehadiran berhasil dikoreksi via backdate. Tercatat di audit trail.');
    }
}
