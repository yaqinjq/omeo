@php($isExpanded = $card['scan'] === $activeCard)
<form method="POST" action="{{ $card['route'] }}" id="attendance-card-{{ $card['scan'] }}" class="attendance-form rounded-[2rem] border bg-white p-5 shadow-sm {{ $card['enabled'] ? 'border-emerald-200 ring-1 ring-emerald-100' : 'border-slate-200' }}" data-scan="{{ $card['scan'] }}" data-form-title="{{ $card['title'] }}">
    @csrf
    <input type="hidden" name="outlet_id" value="{{ $currentOutlet->id }}">
    <input type="hidden" name="latitude" class="lat-field">
    <input type="hidden" name="longitude" class="lng-field">
    <input type="hidden" name="accuracy" class="acc-field">
    <input type="hidden" name="location_samples_json" class="samples-json-field">
    <input type="hidden" name="selected_sample_index" class="selected-sample-field">
    <input type="hidden" name="capture_mode" class="capture-mode-field" value="">
    <input type="hidden" name="selfie_photo_data" class="camera-data-field" data-kind="selfie">
    <input type="hidden" name="environment_photo_data" class="camera-data-field" data-kind="environment">

    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-xl font-black text-slate-900">{{ $card['title'] }}</h2>
                <span class="rounded-full px-3 py-1 text-[11px] font-semibold {{ $card['enabled'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $card['enabled'] ? 'Langkah aktif' : 'Belum aktif' }}</span>
            </div>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $card['summary'] }}</p>
        </div>
        <div class="shrink-0 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
            {{ $card['scan'] === 'in' ? 'Check In' : 'Check Out' }}
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">GPS <strong class="block mt-1" data-gps-copy>belum dicek</strong></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">Kamera <strong class="block mt-1" data-evidence-copy>belum lengkap</strong></div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">Radius <strong class="block mt-1">{{ $radiusLimit }} meter</strong></div>
    </div>

    @if($isExpanded)
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @foreach([
                ['kind' => 'selfie', 'title' => 'Selfie Live', 'helper' => 'Pakai kamera depan dan pastikan wajah terlihat jelas.', 'button' => 'Ambil Selfie'],
                ['kind' => 'environment', 'title' => 'Foto Lingkungan', 'helper' => 'Pakai kamera belakang dan arahkan ke area kerja.', 'button' => 'Ambil Foto'],
            ] as $capture)
                <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-base font-bold text-slate-900">{{ $capture['title'] }}</div>
                            <div class="mt-1 text-sm text-slate-500">{{ $capture['helper'] }}</div>
                        </div>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600" data-status-badge="{{ $capture['kind'] }}">Belum capture</span>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-2xl border border-dashed border-slate-300 bg-white">
                        <img class="camera-preview hidden h-52 w-full object-cover" data-preview="{{ $capture['kind'] }}" alt="Preview {{ $capture['kind'] }}">
                        <div class="camera-placeholder flex h-52 items-center justify-center px-5 text-center text-sm leading-6 text-slate-500" data-placeholder="{{ $capture['kind'] }}">Belum ada snapshot live.</div>
                    </div>

                    <div class="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600" data-size-label="{{ $capture['kind'] }}">Ukuran capture: belum ada</div>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button type="button" class="open-camera-btn rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800" data-target="{{ $capture['kind'] }}">{{ $capture['button'] }}</button>
                        <button type="button" class="reset-capture-btn rounded-2xl border border-slate-300 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-100" data-target="{{ $capture['kind'] }}">Ambil Ulang</button>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="mt-5 rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            GPS akan dicek otomatis saat tombol dikirim. Jika akurasi masih buruk, coba ulangi di area yang lebih terbuka.
        </div>

        <button type="submit" class="mt-5 inline-flex w-full items-center justify-center rounded-[1.6rem] px-5 py-4 text-base font-black shadow-sm {{ $card['scan'] === 'in' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-slate-900 text-white hover:bg-slate-800' }}">{{ $card['button'] }}</button>
    @else
        <div class="mt-4 rounded-[1.75rem] border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
            {{ !$attendanceAccessAllowed ? 'Presensi nonaktif sampai kelengkapan profil dan payroll yang diwajibkan selesai.' : ($card['enabled'] ? 'Langkah ini siap dipakai.' : 'Panel ini akan aktif setelah langkah sebelumnya selesai.') }}
        </div>
        <button type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-[1.6rem] border border-slate-300 px-5 py-4 text-base font-semibold text-slate-500 opacity-60" disabled>{{ $card['button'] }}</button>
    @endif
</form>
