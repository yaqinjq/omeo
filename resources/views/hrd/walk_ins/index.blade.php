@extends('layouts.app')

@section('page_title', 'Walk In Interview')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Walk In Interview</h1>
        <p class="mt-1 text-sm text-muted">Kelola event, posisi, pendaftar, referral HRD, QR check-in, dan screening.</p>
      </div>
      <a href="{{ route('dashboard.walk-ins.create') }}" class="btn-primary">Tambah Event</a>
    </div>
  </div>

  <div class="card p-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
      <label class="text-sm font-semibold">Status
        <select name="status" class="mt-1 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
          <option value="">Semua</option>
          @foreach($statuses as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
          @endforeach
        </select>
      </label>
      <button class="btn" type="submit">Filter</button>
      <a href="{{ route('dashboard.walk-ins.index') }}" class="btn-ghost">Reset</a>
    </form>
  </div>

  <div class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-900">
          <tr>
            <th class="px-4 py-3">Event</th>
            <th class="px-4 py-3">Tanggal</th>
            <th class="px-4 py-3">Lokasi</th>
            <th class="px-4 py-3">Pendaftar</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          @forelse($events as $event)
            <tr>
              <td class="px-4 py-3">
                <div class="font-semibold">{{ $event->title }}</div>
                <div class="text-xs text-muted">{{ route('walk-ins.show', $event) }}</div>
                @auth
                  <div class="mt-1 text-xs text-muted">Referral saya: {{ route('walk-ins.show', $event) }}?ref={{ $event->referralCodeFor(auth()->user()) }}</div>
                @endauth
              </td>
              <td class="px-4 py-3">{{ $event->event_date?->format('d/m/Y') }}<div class="text-xs text-muted">{{ $event->start_time ? substr($event->start_time, 0, 5) : '-' }} - {{ $event->end_time ? substr($event->end_time, 0, 5) : '-' }}</div></td>
              <td class="px-4 py-3">{{ $event->location ?: '-' }}</td>
              <td class="px-4 py-3">{{ $event->registrations_count }}</td>
              <td class="px-4 py-3"><span class="badge">{{ ucfirst($event->status) }}</span></td>
              <td class="px-4 py-3 text-right">
                <div class="flex flex-wrap justify-end gap-2">
                  <a href="{{ route('walk-ins.show', $event) }}" target="_blank" class="btn-ghost">Preview</a>
                  <a href="{{ route('dashboard.walk-ins.participants', $event) }}" class="btn-outline">Peserta</a>
                  <a href="{{ route('dashboard.walk-ins.checkin-qr', $event) }}" class="btn-outline">QR</a>
                  <a href="{{ route('dashboard.walk-ins.edit', $event) }}" class="btn-outline">Edit</a>
                  <form method="POST" action="{{ route('dashboard.walk-ins.destroy', $event) }}" onsubmit="return confirm('Hapus event walk-in ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn-danger" type="submit">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-muted">Belum ada event Walk In.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $events->links() }}
</div>
@endsection
