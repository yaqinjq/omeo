<div id="cameraCaptureModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm">
    <div class="w-full max-w-xl rounded-[2rem] bg-white p-5 shadow-2xl">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Live camera</div>
                <h2 class="mt-2 text-xl font-bold text-slate-900">Ambil snapshot langsung</h2>
                <p id="cameraCaptureLabel" class="mt-2 text-sm leading-6 text-slate-600">Arahkan kamera sesuai petunjuk lalu tekan ambil snapshot.</p>
            </div>
            <button type="button" id="closeCameraModal" class="rounded-full border border-slate-200 p-2 text-slate-500 hover:bg-slate-50">?</button>
        </div>
        <div class="mt-4 overflow-hidden rounded-[1.5rem] bg-slate-950">
            <video id="cameraVideo" class="aspect-[4/3] w-full object-cover" autoplay playsinline muted></video>
            <canvas id="cameraCanvas" class="hidden"></canvas>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <button type="button" id="retryCameraBtn" class="rounded-2xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ganti / Ulang Kamera</button>
            <button type="button" id="captureCameraBtn" class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Gunakan Snapshot Ini</button>
        </div>
    </div>
</div>

@unless($attendanceAccessAllowed)
<div id="attendanceLockModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-[2rem] bg-white p-6 shadow-2xl">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl text-amber-700">!</div>
        <h2 class="mt-4 text-center text-2xl font-bold text-slate-900">{{ $attendanceLockTitle }}</h2>
        <p class="mt-3 text-center text-sm leading-6 text-slate-600">{{ data_get($attendanceEligibility, 'message') }}</p>
        <button type="button" id="attendanceLockModalClose" class="mt-5 w-full rounded-2xl bg-slate-100 px-4 py-3 font-semibold text-slate-700 hover:bg-slate-200">Saya Mengerti</button>
    </div>
</div>
@endunless

<div id="tourPresensiModal" class="fixed inset-0 z-[65] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-2xl rounded-[2rem] bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Panduan</div>
                <h2 class="mt-2 text-2xl font-black text-slate-900">Cara presensi yang cepat di HP</h2>
            </div>
            <button type="button" id="tourPresensiClose" class="rounded-full border border-slate-200 p-2 text-slate-500 hover:bg-slate-50">?</button>
        </div>
        <div class="mt-5 grid gap-3 text-sm text-slate-700 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">Izinkan GPS dan kamera saat browser meminta izin.</div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">Gunakan tombol aktif saja agar tidak bingung antara hadir dan pulang.</div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">Untuk iPhone, ulangi cek GPS sekali jika akurasi masih tinggi.</div>
        </div>
        <button type="button" id="tourPresensiCloseFooter" class="mt-5 w-full rounded-2xl bg-slate-900 px-4 py-3 font-semibold text-white hover:bg-slate-800">Mulai Pakai Presensi</button>
    </div>
</div>
