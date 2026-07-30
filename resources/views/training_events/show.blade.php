@extends('layouts.app')
@section('content')
@php
  $registeredCount = $event->participants->whereIn('status', ['registered', 'checked_in', 'attended'])->count();
  $checkedInCount = $event->participants->whereIn('status', ['checked_in', 'attended'])->count();
  $attendedCount = $event->participants->where('status', 'attended')->count();
@endphp
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Training Event</div>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $event->title }}</h1>
        <div class="mt-2 text-sm text-slate-600">
          {{ strtoupper($event->event_type) }} | {{ ucfirst($event->status) }} | Trainer: {{ $event->mentor?->name ?? '-' }} | {{ optional($event->starts_at)->format('d/m/Y H:i') ?: '-' }}
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('training-events.edit', $event) }}" class="btn-primary">Edit Event</a>
        <a href="{{ route('training-events.index') }}" class="btn-outline">Kembali</a>
      </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><span class="text-slate-500">Program:</span> {{ $event->program?->name ?? '-' }}</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><span class="text-slate-500">Materi:</span> {{ $event->material?->title ?? '-' }}</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><span class="text-slate-500">Platform:</span> {{ $event->platform ?? '-' }}</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3"><span class="text-slate-500">Lokasi:</span> {{ $event->location_name ?? '-' }}</div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 md:col-span-2">
        <span class="text-slate-500">Meeting URL:</span>
        @if($event->meeting_url)
          <a href="{{ $event->meeting_url }}" target="_blank" class="font-semibold text-blue-700 underline">{{ $event->meeting_url }}</a>
        @else
          -
        @endif
      </div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 md:col-span-2"><span class="text-slate-500">Instruksi:</span> {{ $event->participant_instruction ?: '-' }}</div>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Diundang</div><div class="mt-2 text-2xl font-bold">{{ $event->participants->count() }}</div></div>
    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Daftar</div><div class="mt-2 text-2xl font-bold text-blue-900">{{ $registeredCount }}</div></div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Check-in</div><div class="mt-2 text-2xl font-bold text-amber-900">{{ $checkedInCount }}</div></div>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Attended</div><div class="mt-2 text-2xl font-bold text-emerald-900">{{ $attendedCount }}</div></div>
  </div>

  <div class="card p-5">
    <h2 class="text-lg font-bold text-slate-900">Tambah Undangan Peserta</h2>
    <form method="POST" action="{{ route('training-events.participants.invite', $event) }}" class="mt-4 space-y-3">
      @csrf
      <div class="flex flex-col gap-3 md:flex-row">
        <input type="search" class="js-employee-filter w-full rounded-xl border-slate-300" placeholder="Cari nama, jabatan, departemen...">
        <button type="button" class="js-check-visible btn-outline whitespace-nowrap">Centang yang terlihat</button>
        <button type="button" class="js-uncheck-visible btn-outline whitespace-nowrap">Kosongkan yang terlihat</button>
      </div>
      <div class="max-h-72 overflow-y-auto rounded-2xl border border-slate-200">
        <div class="grid grid-cols-1 divide-y divide-slate-100">
          @foreach($employees as $employee)
            @php($alreadyInvited = $event->participants->contains('employee_id', $employee->id))
            @php($searchText = strtolower(trim($employee->full_name . ' ' . ($employee->position?->name ?? '') . ' ' . ($employee->department?->name ?? '') . ' ' . ($employee->employee_number ?? ''))))
            <label class="js-employee-row flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-slate-50 {{ $alreadyInvited ? 'bg-slate-50 opacity-75' : '' }}" data-search="{{ $searchText }}">
              <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="mt-1 rounded border-slate-300" @disabled($alreadyInvited)>
              <span>
                <span class="block font-semibold text-slate-900">{{ $employee->full_name }}</span>
                <span class="block text-xs text-slate-500">
                  {{ $employee->employee_number ?: '-' }}
                  @if($employee->position) | {{ $employee->position->name }} @endif
                  @if($employee->department) | {{ $employee->department->name }} @endif
                  @if($alreadyInvited) | sudah diundang @endif
                </span>
              </span>
            </label>
          @endforeach
        </div>
      </div>
      <button class="btn-primary" type="submit">Tambahkan Undangan</button>
    </form>
  </div>

  <div class="card p-5">
    <div class="mb-4 flex items-center justify-between gap-3">
      <h2 class="text-lg font-bold text-slate-900">Peserta & Daftar Hadir</h2>
      <div class="text-xs text-slate-500">Trainer dan HRD dapat menandai hadir/tidak hadir dari data ini.</div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <th class="px-3 py-3 text-left font-semibold">Karyawan</th>
            <th class="px-3 py-3 text-left font-semibold">Status</th>
            <th class="px-3 py-3 text-left font-semibold">Daftar</th>
            <th class="px-3 py-3 text-left font-semibold">Check-in</th>
            <th class="px-3 py-3 text-left font-semibold">Bukti</th>
            <th class="px-3 py-3 text-left font-semibold">Update</th>
          </tr>
        </thead>
        <tbody>
          @forelse($event->participants as $participant)
            <tr class="border-t border-slate-200 align-top">
              <td class="px-3 py-3">
                <div class="font-semibold text-slate-900">{{ $participant->employee?->full_name ?? '-' }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ $participant->employee?->department?->name ?? '-' }} | {{ $participant->employee?->position?->name ?? '-' }}</div>
                <div class="mt-1 text-xs text-slate-500">Diundang: {{ optional($participant->invited_at)->format('d/m/Y H:i') ?: '-' }} oleh {{ $participant->invitedBy?->name ?? '-' }}</div>
              </td>
              <td class="px-3 py-3">{{ ucfirst(str_replace('_', ' ', $participant->status)) }}</td>
              <td class="px-3 py-3">{{ optional($participant->registered_at)->format('d/m/Y H:i') ?: '-' }}</td>
              <td class="px-3 py-3">
                <div>{{ optional($participant->checked_in_at)->format('d/m/Y H:i') ?: '-' }}</div>
                @if($participant->check_in_latitude || $participant->check_in_longitude)
                  <div class="mt-1 text-xs text-slate-500">{{ $participant->check_in_latitude }}, {{ $participant->check_in_longitude }}</div>
                @endif
                @if($participant->check_in_address)
                  <div class="mt-1 text-xs text-slate-500">{{ $participant->check_in_address }}</div>
                @endif
              </td>
              <td class="px-3 py-3 text-xs">
                <div>
                  Selfie:
                  @if($participant->selfie_photo_path)
                    <a href="{{ asset('storage/' . ltrim($participant->selfie_photo_path, '/')) }}" target="_blank" class="text-blue-700 underline">Lihat</a>
                  @else
                    -
                  @endif
                </div>
                <div class="mt-1">
                  Lingkungan:
                  @if($participant->environment_photo_path)
                    <a href="{{ asset('storage/' . ltrim($participant->environment_photo_path, '/')) }}" target="_blank" class="text-blue-700 underline">Lihat</a>
                  @else
                    -
                  @endif
                </div>
              </td>
              <td class="px-3 py-3">
                <form method="POST" action="{{ route('training-events.participants.update', [$event, $participant]) }}" class="space-y-2">
                  @csrf
                  @method('PATCH')
                  <select name="status" class="w-full rounded-xl border-slate-300 text-sm">
                    @foreach($participantStatuses as $status)
                      <option value="{{ $status }}" @selected($participant->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                  </select>
                  <input name="attendance_note" value="{{ $participant->attendance_note }}" class="w-full rounded-xl border-slate-300 text-xs" placeholder="Catatan trainer/HRD">
                  <button class="btn-outline text-xs" type="submit">Simpan</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-3 py-5 text-center text-slate-500">Belum ada peserta event.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
(function () {
  const filter = document.querySelector('.js-employee-filter');
  const rows = Array.from(document.querySelectorAll('.js-employee-row'));
  const visibleRows = () => rows.filter((row) => row.style.display !== 'none');

  filter?.addEventListener('input', function () {
    const term = this.value.trim().toLowerCase();
    rows.forEach((row) => {
      row.style.display = !term || row.dataset.search.includes(term) ? '' : 'none';
    });
  });

  document.querySelector('.js-check-visible')?.addEventListener('click', function () {
    visibleRows().forEach((row) => {
      const checkbox = row.querySelector('input[type="checkbox"]:not(:disabled)');
      if (checkbox) checkbox.checked = true;
    });
  });

  document.querySelector('.js-uncheck-visible')?.addEventListener('click', function () {
    visibleRows().forEach((row) => {
      const checkbox = row.querySelector('input[type="checkbox"]:not(:disabled)');
      if (checkbox) checkbox.checked = false;
    });
  });
})();
</script>
@endsection
