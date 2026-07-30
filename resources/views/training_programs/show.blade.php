@extends('layouts.app')
@section('content')
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Training HRD</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $program->name }}</h1>
        <p class="mt-2 text-sm text-slate-600">Audience: {{ ucfirst($program->audience_scope) }} | Mentor: {{ $program->mentor?->name ?? '-' }}</p>
        <div class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-slate-700">{{ $program->description ?: '-' }}</div>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" id="openProgramGuide" class="btn-outline">Panduan Halaman Ini</button>
        <a href="{{ route('training-events.create') }}" class="btn-outline">Jadwalkan Event</a>
        <a href="{{ route('training-programs.edit', $program) }}" class="btn-primary">Edit Program</a>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Peserta</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $monitoring['total_participants'] }}</div></div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $monitoring['assigned'] }}</div></div>
    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Sedang berjalan</div><div class="mt-2 text-2xl font-bold text-blue-900">{{ $monitoring['in_progress'] }}</div></div>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Selesai</div><div class="mt-2 text-2xl font-bold text-emerald-900">{{ $monitoring['completed'] }}</div></div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Rata-rata Posttest</div><div class="mt-2 text-2xl font-bold text-amber-900">{{ $monitoring['avg_posttest_score'] ?: '-' }}</div></div>
  </div>

  <div id="programGuideInline" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div class="font-semibold">Cara membaca halaman detail program</div>
        <p class="mt-1 leading-6">Bagian atas menunjukkan ringkasan program. Setelah itu cek urutan materi, lalu monitoring peserta, lalu monitoring per materi untuk menemukan bottleneck peserta yang tidak bisa lanjut.</p>
      </div>
      <button type="button" id="dismissProgramGuideInline" class="text-xs font-semibold underline underline-offset-4">Tutup panduan</button>
    </div>
  </div>

  <div class="card p-6">
    <div class="mb-4 flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold text-slate-900">Urutan Materi</h2>
      <div class="text-xs text-slate-500">Gunakan bagian ini untuk memastikan sequence, pretest, posttest, dan passing score sudah benar.</div>
    </div>
    <div class="space-y-3">
      @forelse($program->materials->sortBy('pivot.sequence_order') as $material)
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <div class="font-semibold text-slate-900">{{ $material->pivot->sequence_order }}. {{ $material->title }}</div>
          <div class="mt-1 text-sm text-slate-600">{{ $material->category }} | Mentor: {{ $material->mentor?->name ?? '-' }} | Pretest: {{ $material->pretestForm?->name ?? '-' }} | Posttest: {{ $material->posttestForm?->name ?? '-' }} | Passing Score: {{ $material->pass_score ?? '-' }}</div>
        </div>
      @empty
        <div class="text-sm text-slate-500">Belum ada materi di program ini.</div>
      @endforelse
    </div>
  </div>

  <div class="card p-6 space-y-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold text-slate-900">Monitoring Peserta</h2>
      <div class="text-xs text-slate-500">Per karyawan: progress program, materi aktif, nilai pretest/posttest rata-rata</div>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-slate-200">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600"><tr><th class="px-3 py-3 text-left font-semibold">Karyawan</th><th class="px-3 py-3 text-left font-semibold">Status</th><th class="px-3 py-3 text-left font-semibold">Progress</th><th class="px-3 py-3 text-left font-semibold">Materi Aktif</th><th class="px-3 py-3 text-left font-semibold">Avg Pretest</th><th class="px-3 py-3 text-left font-semibold">Avg Posttest</th><th class="px-3 py-3 text-left font-semibold">Aktivitas Terakhir</th></tr></thead>
        <tbody>
          @forelse($monitoring['participants'] as $participant)
            <tr class="border-t border-slate-200/80 align-top">
              <td class="px-3 py-3"><div class="font-medium text-slate-900">{{ $participant->employee?->full_name ?? '-' }}</div><div class="text-xs text-slate-500">{{ $participant->employee?->department?->name ?? '-' }} | {{ $participant->employee?->position?->name ?? '-' }}</div></td>
              <td class="px-3 py-3 text-slate-700">{{ ucfirst($participant->status) }}</td>
              <td class="px-3 py-3 text-slate-700">{{ number_format((float) $participant->progress_percent, 0) }}% ({{ $participant->completed_materials_count ?? 0 }} selesai)</td>
              <td class="px-3 py-3 text-slate-700">{{ $participant->currentMaterial?->title ?? $participant->lastMaterial?->title ?? '-' }}</td>
              <td class="px-3 py-3 text-slate-700">{{ $participant->avg_pretest_score ?: '-' }}</td>
              <td class="px-3 py-3 text-slate-700">{{ $participant->avg_posttest_score ?: '-' }}</td>
              <td class="px-3 py-3 text-slate-700">{{ optional($participant->last_activity_at)->format('d/m/Y H:i') ?: optional($participant->started_at)->format('d/m/Y H:i') ?: '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-3 py-5 text-center text-slate-500">Belum ada peserta di program ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card p-6 space-y-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-lg font-semibold text-slate-900">Monitoring Per Materi</h2>
      <div class="text-xs text-slate-500">Gunakan bagian ini jika peserta mentok di materi tertentu.</div>
    </div>
    <div class="space-y-4">
      @forelse($monitoring['materials'] as $materialRow)
        <div class="rounded-2xl border border-slate-200 p-4 space-y-4">
          <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div>
              <div class="font-semibold text-slate-900">{{ $materialRow['material']->pivot->sequence_order ?? '-' }}. {{ $materialRow['material']->title }}</div>
              <div class="text-sm text-slate-500">Mentor: {{ $materialRow['material']->mentor?->name ?? '-' }} | Pretest: {{ $materialRow['material']->pretestForm?->name ?? '-' }} | Posttest: {{ $materialRow['material']->posttestForm?->name ?? '-' }}</div>
            </div>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4 text-xs">
              <div class="rounded-xl bg-slate-50 px-3 py-2">Assigned: <strong>{{ $materialRow['assigned'] }}</strong></div>
              <div class="rounded-xl bg-slate-50 px-3 py-2">Locked: <strong>{{ $materialRow['locked'] }}</strong></div>
              <div class="rounded-xl bg-slate-50 px-3 py-2">In Progress: <strong>{{ $materialRow['in_progress'] }}</strong></div>
              <div class="rounded-xl bg-slate-50 px-3 py-2">Completed: <strong>{{ $materialRow['completed'] }}</strong></div>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 text-sm">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-emerald-900">Rata-rata Pretest: <strong>{{ $materialRow['avg_pretest_score'] ?: '-' }}</strong></div>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-3 text-blue-900">Rata-rata Posttest: <strong>{{ $materialRow['avg_posttest_score'] ?: '-' }}</strong></div>
          </div>
          <div class="overflow-x-auto rounded-2xl border border-slate-200">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-slate-600"><tr><th class="px-3 py-3 text-left font-semibold">Karyawan</th><th class="px-3 py-3 text-left font-semibold">Status Materi</th><th class="px-3 py-3 text-left font-semibold">Progress</th><th class="px-3 py-3 text-left font-semibold">Pretest</th><th class="px-3 py-3 text-left font-semibold">Posttest</th><th class="px-3 py-3 text-left font-semibold">Last Activity</th></tr></thead>
              <tbody>
                @forelse($materialRow['participants'] as $progress)
                  <tr class="border-t border-slate-200/80">
                    <td class="px-3 py-3 text-slate-700">{{ $progress->employee?->full_name ?? '-' }}</td>
                    <td class="px-3 py-3 text-slate-700">{{ ucfirst($progress->status) }}</td>
                    <td class="px-3 py-3 text-slate-700">{{ number_format((float) $progress->progress_percent, 0) }}%</td>
                    <td class="px-3 py-3 text-slate-700">{{ $progress->pretest_score ?? '-' }}</td>
                    <td class="px-3 py-3 text-slate-700">{{ $progress->posttest_score ?? '-' }}</td>
                    <td class="px-3 py-3 text-slate-700">{{ optional($progress->last_activity_at)->format('d/m/Y H:i') ?: optional($progress->updated_at)->format('d/m/Y H:i') ?: '-' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="px-3 py-5 text-center text-slate-500">Belum ada data progres untuk materi ini.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      @empty
        <div class="text-sm text-slate-500">Belum ada materi di program ini.</div>
      @endforelse
    </div>
  </div>

  <div class="card p-6">
    <h2 class="mb-3 text-lg font-semibold text-slate-900">Event Training Terkait</h2>
    <div class="space-y-2">
      @forelse($program->events as $event)
        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 p-4 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="font-medium text-slate-900">{{ $event->title }}</div>
            <div class="text-sm text-slate-500">{{ strtoupper($event->event_type) }} | {{ optional($event->starts_at)->format('d/m/Y H:i') ?: '-' }} | Peserta: {{ $event->participants_count }}</div>
          </div>
          <a href="{{ route('training-events.show', $event) }}" class="btn-outline">Detail Event</a>
        </div>
      @empty
        <div class="text-sm text-slate-500">Belum ada event training terkait.</div>
      @endforelse
    </div>
  </div>
</div>

<div id="programGuideModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4">
  <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-slate-900">Panduan Membaca Detail Program</h2>
        <p class="mt-1 text-sm text-slate-600">Halaman ini membantu HRD menemukan bottleneck peserta yang tidak bisa memulai atau menyelesaikan materi.</p>
      </div>
      <button type="button" id="closeProgramGuide" class="btn-outline">Tutup</button>
    </div>
    <div class="mt-5 grid gap-3 text-sm text-slate-700">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">1. Lihat <strong>Urutan Materi</strong> untuk memeriksa sequence, pretest, posttest, dan passing score.</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">2. Lihat <strong>Monitoring Peserta</strong> untuk mengetahui siapa yang assigned, in progress, atau selesai.</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">3. Lihat <strong>Monitoring Per Materi</strong> jika ada peserta yang mentok di satu materi tertentu.</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">4. Gunakan data nilai pretest/posttest untuk mengecek apakah peserta gagal lanjut karena syarat akademik.</div>
    </div>
    <div class="mt-5 flex justify-end gap-2">
      <button type="button" id="ackProgramGuide" class="btn-primary">Mengerti</button>
    </div>
  </div>
</div>

<script>
(function () {
  const key = 'hrd_training_program_detail_guide_v1';
  const inlineKey = 'hrd_training_program_detail_inline_v1';
  const modal = document.getElementById('programGuideModal');
  const inline = document.getElementById('programGuideInline');
  const openBtn = document.getElementById('openProgramGuide');
  const closeBtn = document.getElementById('closeProgramGuide');
  const ackBtn = document.getElementById('ackProgramGuide');
  const dismissInlineBtn = document.getElementById('dismissProgramGuideInline');

  function openGuide() {
    modal?.classList.remove('hidden');
    modal?.classList.add('flex');
  }

  function closeGuide(persist = true) {
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    if (persist) {
      localStorage.setItem(key, '1');
    }
  }

  if (localStorage.getItem(inlineKey) === '1') {
    inline?.classList.add('hidden');
  }

  if (!localStorage.getItem(key)) {
    openGuide();
  }

  openBtn?.addEventListener('click', openGuide);
  closeBtn?.addEventListener('click', function () { closeGuide(true); });
  ackBtn?.addEventListener('click', function () { closeGuide(true); });
  dismissInlineBtn?.addEventListener('click', function () {
    inline?.classList.add('hidden');
    localStorage.setItem(inlineKey, '1');
  });
})();
</script>
@endsection
