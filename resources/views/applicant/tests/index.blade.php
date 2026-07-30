@extends('layouts.app')

@section('content')
<div class="bg-white border rounded-lg p-4 space-y-4">
  <div>
    <h1 class="text-lg font-semibold">Tes Seleksi</h1>
    <p class="text-sm text-slate-600">Status test hanya menampilkan progress, tanpa nilai hasil detail.</p>
  </div>

  @if(!$candidate)
    <div class="p-3 rounded bg-amber-50 text-amber-800 border border-amber-200 text-sm">
      Akun Anda belum terhubung ke data kandidat. Hubungi HRD.
    </div>
  @endif

  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
    @foreach(($types ?? []) as $type => $label)
      @php
        /** @var \App\Models\FormAssignment|null $assignment */
        $assignment = $assignmentsByType[$type] ?? null;
        $status = $assignment?->status ?? 'locked';
        $isOpenedNotStarted = $assignment && $status === 'opened' && !optional($assignment->attempt)->started_at;
        $isOpenedStarted = $assignment && $status === 'opened' && optional($assignment->attempt)->started_at;
      @endphp
      <div class="border rounded-lg p-4 space-y-2">
        <div class="flex items-center justify-between gap-2">
          <div class="font-semibold">{{ $label }}</div>
          <span class="px-2 py-1 rounded text-xs {{ $status === 'submitted' ? 'bg-green-100 text-green-700' : ($status === 'opened' ? 'bg-blue-100 text-blue-700' : ($status === 'expired' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600')) }}">{{ $status }}</span>
        </div>

        <div class="text-xs text-slate-500">
          {{ $assignment?->form?->name ?? 'Belum ada form ditugaskan' }}
        </div>

        @if($isOpenedNotStarted)
          <div class="text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded p-2">
            Test sudah aktif, belum dimulai. Klik Mulai Test.
          </div>
        @elseif($isOpenedStarted)
          <div class="text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded p-2">
            Test sedang berjalan.
          </div>
        @elseif($assignment?->status === 'expired')
          <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded p-2">
            Waktu test habis.
          </div>
        @elseif($assignment?->status === 'submitted')
          <div class="text-xs text-green-700 bg-green-50 border border-green-200 rounded p-2">
            Test sudah selesai dikirim.
          </div>
        @endif

        @if($assignment && $status === 'opened')
          <a href="{{ route('applicant.tests.show', $assignment) }}" class="inline-block px-3 py-2 rounded bg-slate-900 text-white text-sm">Mulai Test</a>
        @elseif($assignment && in_array($status, ['submitted', 'expired'], true))
          <a href="{{ route('applicant.tests.show', $assignment) }}" class="inline-block px-3 py-2 rounded border text-sm">Lihat Detail Status</a>
        @else
          <div class="text-sm text-slate-500">Test belum dibuka oleh HRD.</div>
        @endif
      </div>
    @endforeach
  </div>
</div>

@if(($showPendingStartPopup ?? false) && $popupAssignment)
  <div id="pendingStartModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity duration-300 px-4" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
      <div class="bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white text-center">
        <div class="mx-auto bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mb-4 backdrop-blur-md">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L8 13l1.75-6L12 9l2.25-2L16 13l-1.75 4h-4.5z"></path></svg>
        </div>
        <h2 class="text-2xl font-bold">Test Sudah Aktif</h2>
        <p class="text-emerald-100 text-sm mt-1">Segera mulai test Anda.</p>
      </div>
      <div class="p-6 md:p-8">
        <p class="text-sm text-gray-600 dark:text-gray-300 text-center mb-6">Test sudah aktif. Silakan mulai sekarang. Klik Mulai untuk memulai timer.</p>
        <div class="flex flex-col gap-3">
          <a href="{{ $popupStartUrl }}" class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-center shadow-lg transition">Mulai Test</a>
          <button id="pendingLaterBtn" class="w-full py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-center transition">Nanti</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const modal = document.getElementById('pendingStartModal');
      if (!modal) return;

      const assignmentId = '{{ $popupAssignment->id }}';
      const key = 'pending-test-popup-seen-' + assignmentId;

      if (!localStorage.getItem(key)) {
        modal.classList.remove('hidden');
        localStorage.setItem(key, '1');
      }

      const closeBtn = document.getElementById('pendingLaterBtn');
      if (closeBtn) {
        closeBtn.addEventListener('click', function () {
          modal.style.display = 'none';
        });
      }
    })();
  </script>
@endif
@endsection
