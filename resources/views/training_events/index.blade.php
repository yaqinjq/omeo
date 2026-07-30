@extends('layouts.app')
@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Training Events</h1>
        <p class="mt-1 text-sm text-muted">Kelola undangan, registrasi, live online meeting, dan absensi training.</p>
      </div>
      <a href="{{ route('training-events.create') }}" class="btn-primary">+ Buat Event</a>
    </div>
  </div>

  <div class="card overflow-hidden">
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="px-4 py-3 text-left font-semibold">Event</th>
            <th class="px-4 py-3 text-left font-semibold">Tipe</th>
            <th class="px-4 py-3 text-left font-semibold">Trainer</th>
            <th class="px-4 py-3 text-left font-semibold">Jadwal</th>
            <th class="px-4 py-3 text-left font-semibold">Absensi</th>
            <th class="px-4 py-3 text-left font-semibold">Status</th>
            <th class="px-4 py-3 text-left font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($events as $event)
            <tr class="border-t border-slate-200 align-top">
              <td class="px-4 py-3">
                <div class="font-semibold text-slate-900">{{ $event->title }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $event->program?->name ?? $event->material?->title ?? '-' }}</div>
                @if($event->meeting_url)
                  <div class="mt-1 text-xs text-blue-700">{{ $event->platform ?: 'Online Meeting' }}</div>
                @endif
              </td>
              <td class="px-4 py-3 text-slate-700">{{ strtoupper($event->event_type) }}</td>
              <td class="px-4 py-3 text-slate-700">{{ $event->mentor?->name ?? '-' }}</td>
              <td class="px-4 py-3 text-slate-700">{{ optional($event->starts_at)->format('d/m/Y H:i') ?: '-' }}</td>
              <td class="px-4 py-3 text-slate-700">
                <div>Diundang: {{ $event->participants_count }}</div>
                <div>Daftar: {{ $event->registered_count }}</div>
                <div>Check-in: {{ $event->checked_in_count }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ ucfirst($event->status) }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <a class="btn-outline text-xs" href="{{ route('training-events.show', $event) }}">Detail</a>
                  <a class="btn-outline text-xs" href="{{ route('training-events.edit', $event) }}">Edit</a>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada event training.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div>{{ $events->links() }}</div>
</div>
@endsection
