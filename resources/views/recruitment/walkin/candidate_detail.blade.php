@extends('layouts.app')
@section('title', 'Detail Kandidat — ' . $candidate->candidate_name)
@section('content')
@php
use Carbon\Carbon;

$checkInTime       = $candidate->check_in_time ? Carbon::parse($candidate->check_in_time) : null;
$eventStart        = $candidate->event_start_datetime ? Carbon::parse($candidate->event_start_datetime) : null;

$interviewStatus   = $candidate->interview_status ?? 'pending';
$testStatus        = $candidate->test_status ?? 'pending';
$finalStatus       = $candidate->final_status ?? 'pending';
$testType          = $candidate->test_type ?? null;

$showTestSection      = $interviewStatus === 'passed';
$showPlottingSection  = $interviewStatus === 'passed';

// Timeline steps
$steps = [
    ['label' => 'Terdaftar', 'done' => true,                                                    'icon' => '📝'],
    ['label' => 'Check-In',  'done' => in_array($candidate->registration_status, ['checked_in']),'icon' => '✅'],
    ['label' => 'Interview', 'done' => in_array($interviewStatus, ['passed','failed','skipped']), 'icon' => '🗣️'],
    ['label' => 'Tes',       'done' => in_array($testStatus, ['passed','failed']),               'icon' => '📊'],
    ['label' => 'Final',     'done' => in_array($finalStatus, ['accepted','reserved','rejected']),'icon' => '🏁'],
];

$stepColor = function(string $status, string $field) use ($interviewStatus, $testStatus, $finalStatus, $candidate) {
    return match($field) {
        'registration' => $candidate->registration_status === 'checked_in' ? '#16a34a' : ($candidate->registration_status === 'no_show' ? '#dc2626' : '#6366f1'),
        'interview'    => match($interviewStatus) { 'passed' => '#16a34a', 'failed' => '#dc2626', 'skipped' => '#6b7280', default => '#d1d5db' },
        'test'         => match($testStatus) { 'passed' => '#16a34a', 'failed' => '#dc2626', default => '#d1d5db' },
        'final'        => match($finalStatus) { 'accepted' => '#16a34a', 'reserved' => '#7c3aed', 'rejected' => '#dc2626', default => '#d1d5db' },
        default        => '#16a34a',
    };
};
@endphp

<div class="p-6 max-w-4xl mx-auto space-y-6">

    {{-- BREADCRUMB --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('walkin.index') }}" class="hover:text-indigo-600">Walk-In Events</a>
        <span>›</span>
        <a href="{{ route('walkin.dashboard', $candidate->event_id) }}"
           class="hover:text-indigo-600">{{ $candidate->event_title }}</a>
        <span>›</span>
        <span class="text-gray-800 font-medium">{{ $candidate->candidate_name }}</span>
    </div>

    @if(session('success'))
    <div class="px-4 py-3 rounded-xl text-sm font-medium text-white" style="background:#22c55e">
        {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div class="px-4 py-3 rounded-xl text-sm text-red-700" style="background:#fee2e2">
        @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
    </div>
    @endif

    {{-- ══ SECTION 1: INFO KANDIDAT ══════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 flex items-center justify-between" style="background:#6366f1">
            <span class="font-bold text-white text-sm">Informasi Kandidat</span>
            <span class="font-mono font-bold text-white text-lg tracking-widest">
                {{ $candidate->registration_number }}
            </span>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-xs text-gray-400 mb-0.5">Nama Lengkap</div>
                <div class="font-semibold text-gray-800 text-base">{{ $candidate->candidate_name }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">Nomor HP</div>
                <div class="font-semibold text-gray-800">{{ $candidate->candidate_phone }}</div>
            </div>
            @if($candidate->candidate_email)
            <div>
                <div class="text-xs text-gray-400 mb-0.5">Email</div>
                <div class="text-gray-700">{{ $candidate->candidate_email }}</div>
            </div>
            @endif
            @if($candidate->ig_account)
            <div>
                <div class="text-xs text-gray-400 mb-0.5">Instagram</div>
                <div class="text-gray-700">@{{ $candidate->ig_account }}</div>
            </div>
            @endif
            <div>
                <div class="text-xs text-gray-400 mb-0.5">Posisi Dilamar</div>
                <div class="text-gray-700">{{ $candidate->applied_position ?: '-' }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">Direferral Oleh</div>
                <div class="text-gray-700">{{ $candidate->referred_by_name ?: '-' }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">Event</div>
                <div class="text-gray-700">
                    {{ $candidate->event_title }}
                    @if($eventStart) · {{ $eventStart->translatedFormat('d M Y') }} @endif
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-400 mb-0.5">Waktu Check-In</div>
                <div class="text-gray-700">{{ $checkInTime ? $checkInTime->format('d M Y, H:i') . ' WIB' : '—' }}</div>
            </div>
        </div>
    </div>

    {{-- ══ SECTION 2: PROGRESS TIMELINE ══════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Progress Seleksi</h2>
        <div class="flex items-center gap-0">
            @php
            $timelineSteps = [
                ['label' => 'Terdaftar', 'color' => '#16a34a',    'done' => true],
                ['label' => 'Check-In',  'color' => $candidate->registration_status === 'checked_in' ? '#16a34a' : ($candidate->registration_status === 'no_show' ? '#dc2626' : '#d1d5db'), 'done' => $candidate->registration_status !== 'registered'],
                ['label' => 'Interview', 'color' => match($interviewStatus) { 'passed'=>'#16a34a','failed'=>'#dc2626','skipped'=>'#6b7280',default=>'#d1d5db' }, 'done' => $interviewStatus !== 'pending'],
                ['label' => 'Tes',       'color' => match($testStatus)      { 'passed'=>'#16a34a','failed'=>'#dc2626',default=>'#d1d5db' },                    'done' => $testStatus !== 'pending'],
                ['label' => 'Final',     'color' => match($finalStatus)     { 'accepted'=>'#16a34a','reserved'=>'#7c3aed','rejected'=>'#dc2626',default=>'#d1d5db' }, 'done' => $finalStatus !== 'pending'],
            ];
            @endphp
            @foreach($timelineSteps as $idx => $step)
            <div class="flex items-center {{ $idx < count($timelineSteps)-1 ? 'flex-1' : '' }}">
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
                         style="background:{{ $step['color'] }}">
                        {{ $idx + 1 }}
                    </div>
                    <div class="text-xs mt-1 text-center font-medium"
                         style="color:{{ $step['done'] ? $step['color'] : '#9ca3af' }}">
                        {{ $step['label'] }}
                    </div>
                </div>
                @if($idx < count($timelineSteps)-1)
                <div class="flex-1 h-0.5 mx-1 mb-4"
                     style="background:{{ $step['done'] ? $step['color'] : '#e5e7eb' }}"></div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- ══ SECTION 3: UPDATE INTERVIEW ═══════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3" style="background:#0ea5e9">
            <span class="font-bold text-white text-sm">🗣️ Update Status Interview</span>
        </div>
        <div class="p-5">
            @if($candidate->interviewer_name)
            <p class="text-xs text-gray-400 mb-4">
                Terakhir diupdate oleh <strong>{{ $candidate->interviewer_name }}</strong>
            </p>
            @endif

            <form method="POST" action="{{ route('walkin.candidate.interview', $candidate->id) }}">
                @csrf
                <div class="flex flex-wrap gap-3 mb-4">
                    @foreach(['passed' => ['✅ Lolos','#16a34a','#dcfce7'], 'failed' => ['❌ Tidak Lolos','#dc2626','#fee2e2'], 'skipped' => ['⏭️ Skip','#6b7280','#f3f4f6']] as $val => $opt)
                    <label class="flex items-center gap-2 cursor-pointer px-4 py-2 rounded-xl border-2 text-sm font-semibold transition-all"
                           style="{{ $interviewStatus === $val ? 'border-color:'.$opt[1].';background:'.$opt[2].';color:'.$opt[1] : 'border-color:#e5e7eb;color:#6b7280' }}">
                        <input type="radio" name="interview_status" value="{{ $val }}"
                               class="sr-only" {{ $interviewStatus === $val ? 'checked' : '' }}>
                        {{ $opt[0] }}
                    </label>
                    @endforeach
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Catatan Interview (opsional)</label>
                    <textarea name="interview_notes" rows="3"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                              placeholder="Tulis catatan hasil interview...">{{ $candidate->interview_notes }}</textarea>
                </div>
                <button type="submit"
                        class="px-5 py-2 rounded-xl text-sm font-semibold text-white"
                        style="background:#0ea5e9">
                    Simpan Status Interview
                </button>
            </form>
        </div>
    </div>

    {{-- ══ SECTION 4: UPDATE TES (hanya jika interview passed) ═══════════════ --}}
    @if($showTestSection)
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3" style="background:#f59e0b">
            <span class="font-bold text-white text-sm">📊 Input Hasil Tes</span>
        </div>
        <div class="p-5">
            <form method="POST" action="{{ route('walkin.candidate.test', $candidate->id) }}">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Jenis Tes</label>
                        <select name="test_type"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-300">
                            <option value="differensial_disc" {{ $testType === 'differensial_disc' ? 'selected' : '' }}>Diferensial & DISC</option>
                            <option value="iq"                {{ $testType === 'iq'                ? 'selected' : '' }}>IQ</option>
                            <option value="none"              {{ $testType === 'none'              ? 'selected' : '' }}>Tidak Ada Tes</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Skor (0–100)</label>
                        <input type="number" name="test_score" min="0" max="100" step="0.01"
                               value="{{ $candidate->test_score !== null ? number_format((float)$candidate->test_score, 2) : '' }}"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-300"
                               placeholder="Contoh: 75">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status Tes</label>
                        <div class="flex gap-2 pt-1">
                            @foreach(['passed' => ['✅ Lolos','#16a34a'], 'failed' => ['❌ Tidak Lolos','#dc2626'], 'pending' => ['⏳ Pending','#d97706']] as $val => $opt)
                            <label class="flex-1 text-center cursor-pointer px-2 py-2 rounded-xl border-2 text-xs font-semibold"
                                   style="{{ $testStatus === $val ? 'border-color:'.$opt[1].';color:'.$opt[1] : 'border-color:#e5e7eb;color:#9ca3af' }}">
                                <input type="radio" name="test_status" value="{{ $val }}" class="sr-only"
                                       {{ $testStatus === $val ? 'checked' : '' }}>
                                {{ $opt[0] }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <button type="submit"
                        class="px-5 py-2 rounded-xl text-sm font-semibold text-white"
                        style="background:#f59e0b">
                    Simpan Hasil Tes
                </button>
            </form>
        </div>
    </div>
    @endif

    {{-- ══ SECTION 5: PLOTTING (hanya jika interview passed) ═════════════════ --}}
    @if($showPlottingSection)
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3" style="background:#16a34a">
            <span class="font-bold text-white text-sm">🏢 Plotting Penempatan</span>
        </div>
        <div class="p-5">
            <form method="POST" action="{{ route('walkin.candidate.plotting', $candidate->id) }}">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Outlet</label>
                        <select name="plotting_outlet_id"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300">
                            <option value="">— Belum ditentukan —</option>
                            @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" {{ $candidate->plotting_outlet_id == $outlet->id ? 'selected' : '' }}>
                                {{ $outlet->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Posisi / Jabatan</label>
                        <input type="text" name="plotting_position"
                               value="{{ $candidate->plotting_position }}"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300"
                               placeholder="Contoh: Crew Kitchen">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status Final</label>
                        <div class="flex flex-wrap gap-2 pt-1">
                            @foreach(['accepted' => ['🎉 Diterima','#16a34a'], 'reserved' => ['📋 Cadangan','#7c3aed'], 'rejected' => ['❌ Ditolak','#dc2626'], 'pending' => ['⏳ Pending','#d97706']] as $val => $opt)
                            <label class="cursor-pointer px-3 py-1.5 rounded-xl border-2 text-xs font-semibold"
                                   style="{{ $finalStatus === $val ? 'border-color:'.$opt[1].';color:'.$opt[1] : 'border-color:#e5e7eb;color:#9ca3af' }}">
                                <input type="radio" name="final_status" value="{{ $val }}" class="sr-only"
                                       {{ $finalStatus === $val ? 'checked' : '' }}>
                                {{ $opt[0] }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Catatan</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300"
                                  placeholder="Catatan tambahan...">{{ $candidate->notes }}</textarea>
                    </div>
                </div>
                @if($candidate->outlet_name)
                <p class="text-xs text-gray-400 mb-3">Outlet saat ini: <strong>{{ $candidate->outlet_name }}</strong></p>
                @endif
                <button type="submit"
                        class="px-5 py-2 rounded-xl text-sm font-semibold text-white"
                        style="background:#16a34a">
                    Simpan Plotting
                </button>
            </form>
        </div>
    </div>
    @endif

</div>

<script>
// Radio button visual toggle
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const siblings = document.querySelectorAll(`input[name="${this.name}"]`);
        siblings.forEach(sib => {
            const label = sib.closest('label');
            if (!label) return;
            if (sib.checked) {
                label.style.borderColor = label.dataset.color || '#6366f1';
                label.style.color = label.dataset.color || '#6366f1';
            } else {
                label.style.borderColor = '#e5e7eb';
                label.style.color = '#9ca3af';
            }
        });
    });
});
</script>
@endsection
