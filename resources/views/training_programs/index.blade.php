@extends('layouts.app')
@section('content')
<div class="space-y-6">
  @if(!empty($moduleWarning))
    <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $moduleWarning }}</div>
  @endif

  <div class="card p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Training HRD</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Program Training / Kurikulum</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Area kerja HRD untuk menyusun program, memilih target audience, menyusun urutan materi, dan memantau kesiapan peserta training.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" id="openTrainingGuide" class="btn-outline">Panduan Modul</button>
        @if(($schemaReady ?? true))
          <a href="{{ route('training-programs.create') }}" class="btn-primary">+ Program Baru</a>
        @else
          <span class="btn-outline opacity-70">Schema LMS belum siap</span>
        @endif
      </div>
    </div>
  </div>

  <div id="trainingGuideInline" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div class="font-semibold">Urutan kerja yang disarankan untuk HRD</div>
        <p class="mt-1 leading-6">1) Buat program, 2) susun urutan materi, 3) pastikan audience tepat, 4) cek detail program untuk memonitor peserta dan progres.</p>
      </div>
      <button type="button" id="dismissTrainingGuideInline" class="text-xs font-semibold underline underline-offset-4">Tutup panduan</button>
    </div>
  </div>

  <div class="grid gap-4 xl:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah program</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $programs->total() }}</div></div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Halaman aktif</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $programs->count() }}</div></div>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Program aktif</div><div class="mt-2 text-2xl font-bold text-emerald-900">{{ collect($programs->items())->where('is_active', true)->count() }}</div></div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Perlu dicek</div><div class="mt-2 text-sm font-semibold text-amber-900">Buka detail program untuk monitor peserta, lock materi, dan nilai training.</div></div>
  </div>

  <div class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="px-4 py-3 text-left font-semibold">Program</th>
            <th class="px-4 py-3 text-left font-semibold">Audience</th>
            <th class="px-4 py-3 text-left font-semibold">Mentor</th>
            <th class="px-4 py-3 text-left font-semibold">Materi</th>
            <th class="px-4 py-3 text-left font-semibold">Peserta</th>
            <th class="px-4 py-3 text-left font-semibold">Status</th>
            <th class="px-4 py-3 text-left font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($programs as $program)
            <tr class="border-t border-slate-200/80 align-top">
              <td class="px-4 py-4">
                <div class="font-semibold text-slate-900">{{ $program->name }}</div>
                <div class="mt-1 text-xs leading-5 text-slate-500">{{ \Illuminate\Support\Str::limit($program->description, 110) }}</div>
              </td>
              <td class="px-4 py-4 text-slate-700">{{ ucfirst($program->audience_scope) }} @if($program->department?->name) - {{ $program->department->name }} @endif @if($program->position?->name) - {{ $program->position->name }} @endif</td>
              <td class="px-4 py-4 text-slate-700">{{ $program->mentor?->name ?? '-' }}</td>
              <td class="px-4 py-4 text-slate-700">{{ $program->materials_count }}</td>
              <td class="px-4 py-4 text-slate-700">{{ $program->enrollments_count }}</td>
              <td class="px-4 py-4">
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $program->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $program->is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-wrap gap-2">
                  <a class="btn-outline text-xs" href="{{ route('training-programs.show', $program) }}">Detail & Monitor</a>
                  <a class="btn-outline text-xs" href="{{ route('training-programs.edit', $program) }}">Edit</a>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada program training.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $programs->links() }}</div>
</div>

<div id="trainingGuideModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4">
  <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-slate-900">Panduan Singkat Modul Training HRD</h2>
        <p class="mt-1 text-sm text-slate-600">Panduan ini dibuat untuk membantu HRD menjalankan modul tanpa harus menebak urutan kerja.</p>
      </div>
      <button type="button" id="closeTrainingGuide" class="btn-outline">Tutup</button>
    </div>
    <div class="mt-5 grid gap-3 text-sm text-slate-700">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">1. Mulai dari <strong>Program Baru</strong> jika ingin membuat kurikulum training baru.</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">2. Isi audience dengan teliti agar peserta yang dituju otomatis masuk ke program yang benar.</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">3. Setelah program dibuat, buka <strong>Detail & Monitor</strong> untuk melihat urutan materi, progres peserta, dan nilai training.</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">4. Jika peserta tidak bisa lanjut materi, cek urutan materi dan hasil pretest/posttest di halaman detail program.</div>
    </div>
    <div class="mt-5 flex justify-end gap-2">
      <button type="button" id="skipTrainingGuide" class="btn-primary">Mengerti</button>
    </div>
  </div>
</div>

<script>
(function () {
  const key = 'hrd_training_programs_guide_v1';
  const inlineKey = 'hrd_training_programs_inline_v1';
  const modal = document.getElementById('trainingGuideModal');
  const inline = document.getElementById('trainingGuideInline');
  const openBtn = document.getElementById('openTrainingGuide');
  const closeBtn = document.getElementById('closeTrainingGuide');
  const skipBtn = document.getElementById('skipTrainingGuide');
  const dismissInlineBtn = document.getElementById('dismissTrainingGuideInline');

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
  skipBtn?.addEventListener('click', function () { closeGuide(true); });
  dismissInlineBtn?.addEventListener('click', function () {
    inline?.classList.add('hidden');
    localStorage.setItem(inlineKey, '1');
  });
})();
</script>
@endsection
