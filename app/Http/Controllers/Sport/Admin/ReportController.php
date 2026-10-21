<?php

namespace App\Http\Controllers\Sport\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sport\Attendance;
use App\Models\Sport\CancellationRequest;
use App\Models\Sport\Event;
use App\Models\Sport\NonMkoAttendance;
use App\Models\Sport\Registration;
use App\Models\Sport\ReportSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Dashboard & laporan Admin Finance (Spesifikasi v1.1 Section 8). Angka
 * peserta unik dan kehadiran (orang-sesi) SENGAJA dilabel terpisah — jangan
 * digabung jadi satu angka (lihat acceptance criteria #7).
 *
 * Filter grup peserta (all|mko|non_mko) — keputusan terbaru: filter ini
 * MEMBATASI angka peserta/kehadiran & baris peserta, TAPI TIDAK mengubah
 * `total_events` (tetap dihitung dari periode+sport saja). Lihat
 * includeMko()/includeNonMko() di bawah — dipakai konsisten di summary,
 * monthly, per-event, dan quarterly, plus ReportExportController.
 *
 * Quarter — keputusan terbaru MENGGANTIKAN default kalender yang sempat
 * dipakai sebelumnya: laporan triwulanan Owner HARUS pakai fiscal quarter,
 * tapi bulan mulai tahun fiskal belum ditentukan. Selama
 * ReportSetting::current()->fiscal_year_start_month masih NULL (default —
 * TIDAK pernah diisi Januari/Agustus/kalender oleh kode ini), buildQuarterly()
 * mengembalikan SATU baris "periode kustom" dari filter from/to yang
 * berjalan, berlabel eksplisit "bukan quarter fiskal" — tidak pernah
 * memberi label Q1-Q4 atau menyebut dirinya laporan fiskal sebelum
 * dikonfigurasi Admin Finance sendiri.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $eventIds = $this->scopedEventQuery($filters)->pluck('id');

        $summary = $this->buildSummary($eventIds, $filters['group']);
        $monthly = $this->buildMonthly($eventIds, $filters['group']);
        $perEvent = $this->buildPerEvent($eventIds, $filters['group']);
        $quarterly = $this->buildQuarterly($eventIds, $filters['group'], $filters);

        $sports = Event::query()->select('sport')->distinct()->orderBy('sport')->pluck('sport');

        return view('sport.admin.reports.index', [
            'filters' => $filters,
            'sports' => $sports,
            'summary' => $summary,
            'monthly' => $monthly,
            'perEvent' => $perEvent,
            'quarterly' => $quarterly,
            'fiscalConfigured' => ReportSetting::current()->isFiscalConfigured(),
        ]);
    }

    public function editFiscalSettings(): View
    {
        return view('sport.admin.reports.fiscal_settings', ['setting' => ReportSetting::current()]);
    }

    public function updateFiscalSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fiscal_year_start_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $setting = ReportSetting::query()->first();
        $payload = [
            'fiscal_year_start_month' => $data['fiscal_year_start_month'] ?? null,
            'updated_by_user_id' => $request->user()->id,
        ];

        if ($setting) {
            $setting->update($payload);
        } else {
            ReportSetting::create($payload);
        }

        $message = ($data['fiscal_year_start_month'] ?? null)
            ? "Bulan mulai tahun fiskal diset ke bulan ke-{$data['fiscal_year_start_month']}. Laporan triwulanan sekarang memakai quarter fiskal."
            : 'Bulan mulai tahun fiskal dikosongkan. Laporan triwulanan kembali memakai periode kustom (bukan quarter fiskal) sampai diisi lagi.';

        return back()->with('success', $message);
    }

    public function filters(Request $request): array
    {
        $group = $request->input('group', 'all');

        return [
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'sport' => $request->input('sport'),
            'group' => in_array($group, ['mko', 'non_mko'], true) ? $group : 'all',
        ];
    }

    public function scopedEventQuery(array $filters)
    {
        return Event::query()
            ->when($filters['from'] ?? null, fn ($q) => $q->whereDate('event_date', '>=', $filters['from']))
            ->when($filters['to'] ?? null, fn ($q) => $q->whereDate('event_date', '<=', $filters['to']))
            ->when($filters['sport'] ?? null, fn ($q) => $q->where('sport', $filters['sport']));
    }

    private function includeMko(string $group): bool
    {
        return $group !== 'non_mko';
    }

    private function includeNonMko(string $group): bool
    {
        return $group !== 'mko';
    }

    public function buildSummary($eventIds, string $group = 'all'): array
    {
        $includeMko = $this->includeMko($group);
        $includeNonMko = $this->includeNonMko($group);

        return [
            // Total event SELALU dihitung dari periode+sport saja, tidak pernah dipotong oleh filter grup.
            'total_events' => $eventIds->count(),
            'unique_participants_mko' => $includeMko
                ? Attendance::whereIn('sport_event_id', $eventIds)->distinct('employee_id')->count('employee_id')
                : null,
            'unique_participants_non_mko' => $includeNonMko
                ? NonMkoAttendance::whereIn('sport_event_id', $eventIds)->distinct('sport_participant_id')->count('sport_participant_id')
                : null,
            'attendance_sessions_mko' => $includeMko
                ? Attendance::whereIn('sport_event_id', $eventIds)->count()
                : null,
            'attendance_sessions_non_mko' => $includeNonMko
                ? NonMkoAttendance::whereIn('sport_event_id', $eventIds)->count()
                : null,
            // Pembatalan & "belum terkonfirmasi" murni konsep MKO (Non-MKO tidak punya pendaftaran/pembatalan).
            'cancellations_approved' => $includeMko
                ? CancellationRequest::where('status', CancellationRequest::STATUS_APPROVED)
                    ->whereHas('registration', fn ($q) => $q->whereIn('sport_event_id', $eventIds))
                    ->count()
                : null,
            'not_confirmed' => $includeMko
                ? Registration::whereIn('sport_event_id', $eventIds)
                    ->where('status', Registration::STATUS_REGISTERED)
                    ->whereDoesntHave('attendance')
                    ->whereHas('event', fn ($q) => $q->where('end_at', '<', now()))
                    ->count()
                : null,
        ];
    }

    public function buildMonthly($eventIds, string $group = 'all'): array
    {
        $includeMko = $this->includeMko($group);
        $includeNonMko = $this->includeNonMko($group);

        $events = Event::whereIn('id', $eventIds)->get(['id', 'event_date']);
        $grouped = $events->groupBy(fn ($e) => $e->event_date->format('Y-m'));

        $rows = [];
        foreach ($grouped as $month => $monthEvents) {
            $monthEventIds = $monthEvents->pluck('id');
            $rows[] = [
                'month' => $month,
                'events' => $monthEvents->count(),
                'unique_mko' => $includeMko ? Attendance::whereIn('sport_event_id', $monthEventIds)->distinct('employee_id')->count('employee_id') : null,
                'unique_non_mko' => $includeNonMko ? NonMkoAttendance::whereIn('sport_event_id', $monthEventIds)->distinct('sport_participant_id')->count('sport_participant_id') : null,
                'sessions_mko' => $includeMko ? Attendance::whereIn('sport_event_id', $monthEventIds)->count() : null,
                'sessions_non_mko' => $includeNonMko ? NonMkoAttendance::whereIn('sport_event_id', $monthEventIds)->count() : null,
            ];
        }
        ksort($rows);

        return $rows;
    }

    public function buildPerEvent($eventIds, string $group = 'all'): array
    {
        $includeMko = $this->includeMko($group);
        $includeNonMko = $this->includeNonMko($group);

        $events = Event::whereIn('id', $eventIds)
            ->withCount(['registrations', 'attendances', 'nonMkoAttendances'])
            ->orderByDesc('event_date')
            ->get();

        return $events->map(fn ($e) => [
            'id' => $e->id,
            'sport' => $e->sport,
            'title' => $e->title ?: ucfirst($e->sport),
            'event_date' => $e->event_date->format('Y-m-d'),
            'has_coach' => $e->has_coach,
            'capacity' => $e->capacity,
            // Pendaftaran murni konsep MKO (Non-MKO tidak mendaftar sendiri).
            'registrations' => $includeMko ? $e->registrations_count : null,
            'attendances_mko' => $includeMko ? $e->attendances_count : null,
            'attendances_non_mko' => $includeNonMko ? $e->non_mko_attendances_count : null,
            'status' => $e->status,
        ])->all();
    }

    /**
     * @return array{type: 'unconfigured'|'fiscal', rows: array, note: string}
     */
    public function buildQuarterly($eventIds, string $group = 'all', array $filters = []): array
    {
        $setting = ReportSetting::current();

        if (! $setting->isFiscalConfigured()) {
            return $this->buildUnconfiguredCustomPeriod($eventIds, $group, $filters);
        }

        return $this->buildFiscalQuarterly($eventIds, $group, $setting->fiscal_year_start_month);
    }

    private function buildUnconfiguredCustomPeriod($eventIds, string $group, array $filters): array
    {
        $includeMko = $this->includeMko($group);
        $includeNonMko = $this->includeNonMko($group);

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $label = ($from || $to)
            ? trim(($from ?: '…') . ' s/d ' . ($to ?: '…'))
            : 'Seluruh data (belum ada rentang tanggal dipilih)';

        return [
            'type' => 'unconfigured',
            'note' => 'Fiscal year belum disetel oleh Admin Finance. Ini PERIODE KUSTOM — bukan quarter fiskal, bukan Q1-Q4.',
            'rows' => [[
                'label' => $label,
                'events' => $eventIds->count(),
                'unique_mko' => $includeMko ? Attendance::whereIn('sport_event_id', $eventIds)->distinct('employee_id')->count('employee_id') : null,
                'unique_non_mko' => $includeNonMko ? NonMkoAttendance::whereIn('sport_event_id', $eventIds)->distinct('sport_participant_id')->count('sport_participant_id') : null,
                'sessions_mko' => $includeMko ? Attendance::whereIn('sport_event_id', $eventIds)->count() : null,
                'sessions_non_mko' => $includeNonMko ? NonMkoAttendance::whereIn('sport_event_id', $eventIds)->count() : null,
            ]],
        ];
    }

    private function buildFiscalQuarterly($eventIds, string $group, int $startMonth): array
    {
        $includeMko = $this->includeMko($group);
        $includeNonMko = $this->includeNonMko($group);

        $events = Event::whereIn('id', $eventIds)->get(['id', 'event_date']);
        $grouped = $events->groupBy(fn ($e) => $this->fiscalLabel($e->event_date, $startMonth));

        $rows = [];
        foreach ($grouped as $label => $quarterEvents) {
            $quarterEventIds = $quarterEvents->pluck('id');
            $rows[] = [
                'label' => $label,
                'events' => $quarterEvents->count(),
                'unique_mko' => $includeMko ? Attendance::whereIn('sport_event_id', $quarterEventIds)->distinct('employee_id')->count('employee_id') : null,
                'unique_non_mko' => $includeNonMko ? NonMkoAttendance::whereIn('sport_event_id', $quarterEventIds)->distinct('sport_participant_id')->count('sport_participant_id') : null,
                'sessions_mko' => $includeMko ? Attendance::whereIn('sport_event_id', $quarterEventIds)->count() : null,
                'sessions_non_mko' => $includeNonMko ? NonMkoAttendance::whereIn('sport_event_id', $quarterEventIds)->count() : null,
            ];
        }
        ksort($rows);

        return [
            'type' => 'fiscal',
            'note' => "Fiscal year dimulai bulan ke-{$startMonth}. Label FY{tahun} Q{1-4} mengikuti definisi ini.",
            'rows' => $rows,
        ];
    }

    /**
     * Label "FY{tahun mulai}-Q{1-4}" untuk sebuah tanggal, relatif ke bulan
     * mulai tahun fiskal. Menangani lintas tahun kalender dengan benar:
     * fiscal year diberi nama berdasarkan tahun kalender SAAT fiscal year
     * itu DIMULAI (bulan $startMonth), bukan tahun kalender tanggal itu
     * sendiri. Contoh $startMonth=4 (April): Januari 2026 masuk FY2025-Q4
     * (bulan ke-10 dari fiscal year yang dimulai April 2025), bukan FY2026.
     */
    public function fiscalLabel(Carbon $date, int $startMonth): string
    {
        $monthIndex = ($date->month - $startMonth + 12) % 12; // 0-based, posisi bulan dalam fiscal year
        $quarter = intdiv($monthIndex, 3) + 1;
        $fiscalYear = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return "FY{$fiscalYear}-Q{$quarter}";
    }
}
