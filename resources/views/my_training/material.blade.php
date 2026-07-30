@extends('layouts.app')
@section('content')
@php
  $status = (string) ($progress?->status ?? 'assigned');
  $badgeClass = match ($status) {
    'completed' => 'bg-emerald-100 text-emerald-800',
    'in_progress' => 'bg-blue-100 text-blue-800',
    default => 'bg-amber-100 text-amber-800',
  };
  $youtubeEmbed = null;
  if ($contentUrl && (str_contains($contentUrl, 'youtube.com/watch?v=') || str_contains($contentUrl, 'youtu.be/'))) {
    $youtubeEmbed = str_contains($contentUrl, 'embed')
      ? $contentUrl
      : preg_replace('/watch\?v=([^&]+)/', 'embed/$1', str_replace('youtu.be/', 'youtube.com/embed/', $contentUrl));
  }
@endphp
<div class="space-y-6">
  <div class="card p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Materi Training</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $material->title }}</h1>
        <p class="mt-2 text-sm text-slate-600">Program: {{ $program->name }} | Kategori: {{ $material->category ?: '-' }} | Durasi: {{ $material->duration_minutes ?: '-' }} menit</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('my-training.index') }}" class="btn-outline">Kembali ke My Training</a>
        @if($contentUrl)
          <a href="{{ $contentUrl }}" target="_blank" rel="noopener noreferrer" class="btn-primary">{{ $contentLabel }}</a>
        @endif
      </div>
    </div>
  </div>

  <div class="grid gap-4 xl:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</div>
      <div class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pretest</div>
      <div class="mt-2 text-sm font-semibold text-slate-900">{{ $material->pretestForm?->name ?? 'Tidak ada' }}</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Posttest</div>
      <div class="mt-2 text-sm font-semibold text-slate-900">{{ $material->posttestForm?->name ?? 'Tidak ada' }}</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
      <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Passing Score</div>
      <div class="mt-2 text-sm font-semibold text-slate-900">{{ $material->pass_score ?? '-' }}</div>
    </div>
  </div>

  <div class="grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
    <div class="card p-6 space-y-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Konten Materi</h2>
        <p class="mt-1 text-sm text-slate-600">Buka materi dari area ini agar user tidak bingung membedakan langkah buka konten, mulai progres, dan penyelesaian training.</p>
      </div>

      @if($youtubeEmbed)
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-950 aspect-video">
          <iframe src="{{ $youtubeEmbed }}" class="h-full w-full" allowfullscreen loading="lazy"></iframe>
        </div>
      @elseif($contentUrl)
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
          Konten materi tersedia melalui link eksternal. Gunakan tombol <strong>{{ $contentLabel }}</strong> untuk membuka materi pada tab baru.
        </div>
      @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          Materi ini belum memiliki link konten online. Anda masih bisa membaca ringkasan di bawah, tetapi HRD perlu melengkapi sumber materi agar alurnya lengkap.
        </div>
      @endif

      <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="text-sm font-semibold text-slate-900">Ringkasan / Deskripsi</div>
        <div class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $material->description ?: 'Belum ada deskripsi materi.' }}</div>
      </div>
    </div>

    <div class="space-y-5">
      <div class="card p-5 space-y-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Langkah Berikutnya</h2>
          <p class="mt-1 text-sm text-slate-600">Urutan ini dibuat jelas untuk user probation dan karyawan yang belum terbiasa memakai LMS.</p>
        </div>
        <div class="space-y-2 text-sm text-slate-700">
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">1. Buka dan pelajari materi.</div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">2. Kerjakan pretest/posttest jika tersedia.</div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">3. Tandai selesai setelah semua syarat terpenuhi.</div>
        </div>
      </div>

      <div class="card p-5 space-y-3">
        <div class="text-sm font-semibold text-slate-900">Aksi Materi</div>
        @if(($progress?->status ?? 'assigned') === 'assigned')
          <form method="POST" action="{{ route('my-training.materials.start', [$program, $material]) }}">@csrf<button class="btn-outline w-full justify-center" type="submit">Mulai Materi</button></form>
        @endif
        @if($material->pretestForm && !$progress?->pretest_attempt_id)
          <a href="{{ route('training-assessments.show', [$program, $material, 'pretest']) }}" class="btn-outline w-full justify-center">Kerjakan Pretest</a>
        @endif
        @if($material->posttestForm && !$progress?->posttest_attempt_id && (!$material->pretestForm || $progress?->pretest_attempt_id))
          <a href="{{ route('training-assessments.show', [$program, $material, 'posttest']) }}" class="btn-outline w-full justify-center">Kerjakan Posttest</a>
        @endif
        @if(($progress?->status ?? 'assigned') !== 'completed')
          <form method="POST" action="{{ route('my-training.materials.complete', [$program, $material]) }}">@csrf<button class="btn-primary w-full justify-center" type="submit">Tandai Selesai</button></form>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
