@extends('layouts.app')
@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Trainer Console</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $event->title }}</h1>
        <p class="mt-1 text-sm text-muted">{{ strtoupper($event->event_type) }} | {{ optional($event->starts_at)->format('d/m/Y H:i') ?: '-' }}</p>
        @if($event->meeting_url)
          <a href="{{ $event->meeting_url }}" target="_blank" class="mt-3 inline-flex font-semibold text-blue-700 underline">{{ $event->platform ?: 'Buka Meeting' }}</a>
        @endif
      </div>
      <a href="{{ route('trainer.events.index') }}" class="btn-outline">Kembali</a>
    </div>
  </div>

  <div class="card p-5">
    <h2 class="text-lg font-bold text-slate-900">Daftar Hadir Peserta</h2>
    <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="px-3 py-3 text-left font-semibold">Karyawan</th>
            <th class="px-3 py-3 text-left font-semibold">Status</th>
            <th class="px-3 py-3 text-left font-semibold">Daftar</th>
            <th class="px-3 py-3 text-left font-semibold">Check-in</th>
            <th class="px-3 py-3 text-left font-semibold">Update Absensi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($event->participants as $participant)
            <tr class="border-t border-slate-200 align-top">
              <td class="px-3 py-3">
                <div class="font-semibold text-slate-900">{{ $participant->employee?->full_name ?? '-' }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $participant->employee?->department?->name ?? '-' }} | {{ $participant->employee?->position?->name ?? '-' }}</div>
              </td>
              <td class="px-3 py-3">{{ ucfirst(str_replace('_', ' ', $participant->status)) }}</td>
              <td class="px-3 py-3">{{ optional($participant->registered_at)->format('d/m/Y H:i') ?: '-' }}</td>
              <td class="px-3 py-3">{{ optional($participant->checked_in_at)->format('d/m/Y H:i') ?: '-' }}</td>
              <td class="px-3 py-3">
                <form method="POST" action="{{ route('trainer.events.participants.update', [$event, $participant]) }}" class="space-y-2">
                  @csrf
                  @method('PATCH')
                  <select name="status" class="w-full rounded-xl border-slate-300 text-sm">
                    @foreach($statuses as $status)
                      <option value="{{ $status }}" @selected($participant->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                  </select>
                  <input name="attendance_note" value="{{ $participant->attendance_note }}" class="w-full rounded-xl border-slate-300 text-xs" placeholder="Catatan">
                  <button class="btn-outline text-xs" type="submit">Simpan</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-3 py-5 text-center text-slate-500">Belum ada peserta event.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
