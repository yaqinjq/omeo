<div class="space-y-4">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Info Tambahan</div>
                <h2 class="mt-2 text-lg font-bold text-slate-900">Riwayat dan diagnostik presensi</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Bagian ini tetap tersedia untuk HRD dan karyawan, tetapi dipindahkan ke bawah agar alur mobile tetap fokus pada check in dan check out.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Secondary panel</span>
        </div>
    </section>

    <details open class="group rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Riwayat</div>
                <h3 class="mt-1 text-base font-bold text-slate-900">Presensi 14 hari terakhir</h3>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 transition group-open:bg-slate-900 group-open:text-white">Buka</span>
        </summary>

        <div class="mt-4 space-y-3">
            @forelse($recentSessions as $row)
                @php($rtz = $row->outlet?->timezone ?: 'Asia/Jakarta')
                <div class="rounded-2xl border border-slate-200 p-4 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-semibold text-slate-900">{{ $row->work_date?->format('d-m-Y') }}</div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-700">{{ strtoupper($row->status) }}</span>
                    </div>
                    <div class="mt-2 text-slate-600">Outlet: <strong>{{ $row->outlet?->name ?? '-' }}</strong></div>
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-slate-600">IN: <strong>{{ $row->first_in_at_utc ? $row->first_in_at_utc->setTimezone($rtz)->format('H:i') : '-' }}</strong></div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-slate-600">OUT: <strong>{{ $row->last_out_at_utc ? $row->last_out_at_utc->setTimezone($rtz)->format('H:i') : '-' }}</strong></div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Belum ada data presensi.</div>
            @endforelse
        </div>
    </details>

    <details class="group rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Diagnostik GPS</div>
                <h3 class="mt-1 text-base font-bold text-slate-900">Status perangkat dan lokasi</h3>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 transition group-open:bg-slate-900 group-open:text-white">Buka</span>
        </summary>

        <div id="deviceHintBox" class="mt-4 hidden rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"></div>

        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">GPS browser: <strong id="gpsStateText">belum dicek</strong></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">Akurasi terbaik: <strong id="gpsAccuracyText">-</strong></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">Sampel lokasi: <strong id="gpsSampleInfo">belum ada pembacaan</strong></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">Jarak ke outlet: <strong id="distanceInfo">-</strong> meter</div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">Koordinat outlet: <strong id="outletCoordinatesText">{{ $currentOutlet->latitude ?? '-' }}, {{ $currentOutlet->longitude ?? '-' }}</strong></div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">Koordinat perangkat: <strong id="userCoordinatesText">-</strong></div>
        </div>
    </details>
</div>
