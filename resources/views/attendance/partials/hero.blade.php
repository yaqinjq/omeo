@php
    $statusTone = $attendanceAccessAllowed
        ? ($attendanceState === 'completed' ? 'emerald' : ($activeCard ? 'blue' : 'amber'))
        : 'amber';
    $statusBadge = $attendanceAccessAllowed
        ? ($activeCard === 'in' ? 'Check In' : ($activeCard === 'out' ? 'Check Out' : 'Selesai'))
        : 'Terkunci';
    $shiftText = trim((string) ($session?->shift_code ?? 'Shift belum tercatat'));
@endphp

<section class="space-y-4">
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-500">Presensi Mobile</div>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Presensi cepat untuk iPhone dan Android</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">Fokus utama hanya status hari ini, GPS, kamera, dan tombol besar untuk hadir atau pulang.</p>
            </div>
            <button type="button" id="btnTourPresensi" class="shrink-0 rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Panduan</button>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @include('attendance.partials.mobile-status-card', [
                'eyebrow' => 'Status Hari Ini',
                'title' => $statusLabel,
                'subtitle' => $stateHint,
                'tone' => $statusTone,
                'badge' => $statusBadge,
                'badgeTone' => $statusTone,
            ])

            @include('attendance.partials.mobile-status-card', [
                'eyebrow' => 'GPS & Radius',
                'title' => 'Belum dicek',
                'subtitle' => 'Radius outlet ' . $radiusLimit . ' meter. Akurasi maksimal ' . $accuracyLimit . ' meter.',
                'tone' => 'slate',
                'badge' => 'GPS',
                'badgeTone' => 'slate',
                'extraAttributes' => ['data-gps-summary' => 'true'],
            ])

            @include('attendance.partials.mobile-status-card', [
                'eyebrow' => 'Kamera',
                'title' => 'Belum lengkap',
                'subtitle' => 'Selfie live dan foto lingkungan wajib diambil langsung dari kamera browser.',
                'tone' => 'slate',
                'badge' => 'Live',
                'badgeTone' => 'slate',
                'extraAttributes' => ['data-evidence-summary' => 'true'],
            ])

            @include('attendance.partials.mobile-status-card', [
                'eyebrow' => 'Shift & Jam',
                'title' => $shiftText,
                'subtitle' => 'Jam outlet ' . $nowLocal->format('H:i:s') . ' | Zona waktu ' . $tz,
                'tone' => 'slate',
                'badge' => 'Aktif',
                'badgeTone' => 'slate',
            ])
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" id="checkGpsBtn" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cek GPS</button>
            <button type="button" id="retryGpsBtn" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ulang GPS</button>
            @if($activeCard)
                <a href="#attendance-card-{{ $activeCard }}" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm">{{ $activeCard === 'in' ? 'Buka Check In' : 'Buka Check Out' }}</a>
            @endif
        </div>
    </div>
</section>
