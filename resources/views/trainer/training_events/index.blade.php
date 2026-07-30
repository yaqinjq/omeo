@extends('layouts.app')
@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Trainer Console</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Event Training Saya</h1>
        <p class="mt-1 text-sm text-muted">Daftar sesi training yang Anda ampu sebagai trainer internal.</p>
      </div>
      <a href="{{ route('my-training.index') }}" class="btn-outline">My Training</a>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    @forelse($events as $event)
      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-slate-900">{{ $event->title }}</h2>
            <div class="mt-1 text-sm text-slate-500">{{ strtoupper($event->event_type) }} | {{ ucfirst($event->status) }}</div>
          </div>
          <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $event->participants_count }} peserta</span>
        </div>
        <div class="mt-4 grid gap-2 text-sm text-slate-600">
          <div>Program: {{ $event->program?->name ?? '-' }}</div>
          <div>Materi: {{ $event->material?->title ?? '-' }}</div>
          <div>Jadwal: {{ optional($event->starts_at)->format('d/m/Y H:i') ?: '-' }}</div>
          @if($event->meeting_url)
            <a href="{{ $event->meeting_url }}" target="_blank" class="font-semibold text-blue-700 underline">{{ $event->platform ?: 'Online Meeting' }}</a>
          @endif
        </div>
        <a href="{{ route('trainer.events.show', $event) }}" class="btn-primary mt-4">Buka Absensi</a>
      </div>
    @empty
      <div class="rounded-2xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">Belum ada event yang ditugaskan ke Anda.</div>
    @endforelse
  </div>

  <div>{{ $events->links() }}</div>
</div>
@endsection
