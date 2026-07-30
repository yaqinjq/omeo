@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-4">
  <div class="flex items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold">Rekap Presensi</h1>
    <div class="flex items-center gap-2">
      <button type="button" id="btnTourHrd" class="px-3 py-2 rounded border text-sm">Panduan</button>
      <a href="{{ route('hrd.attendance.export-pdf', request()->query()) }}" class="px-4 py-2 rounded bg-slate-900 text-white">Export PDF</a>
    </div>
  </div>

  @if(!empty($moduleWarning))
    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ $moduleWarning }}</div>
  @endif

  <form method="GET" class="text-sm">
    @php
      $selectedEmployee = $employees->first(fn ($employee) => (string) $employee->id === (string) request('employee_id'));
      $selectedEmployeeLabel = $selectedEmployee
        ? trim(($selectedEmployee->employee_number ? $selectedEmployee->employee_number.' - ' : '').$selectedEmployee->full_name)
        : '';
    @endphp
    <div class="flex flex-wrap items-end gap-3 p-4 bg-white rounded-lg border">
      <div class="w-full sm:w-40">
        <label class="block mb-1">Rentang</label>
        <select name="range" class="w-full border rounded px-3 py-2">
          <option value="day" @selected($range==='day')>Harian</option>
          <option value="week" @selected($range==='week')>Mingguan</option>
          <option value="month" @selected($range==='month')>Bulanan</option>
          <option value="3months" @selected($range==='3months')>3 Bulan</option>
        </select>
      </div>
      <div class="w-full sm:w-44">
        <label class="block mb-1">Tanggal Acuan</label>
        <input type="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="w-full border rounded px-3 py-2">
      </div>
      <div class="hidden h-8 border-l border-gray-300 mx-1 md:block"></div>
      <div class="w-full sm:w-44">
        <label class="block mb-1">Dari Tanggal</label>
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full border rounded px-3 py-2">
      </div>
      <div class="w-full sm:w-44">
        <label class="block mb-1">Hingga Tanggal</label>
        <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full border rounded px-3 py-2">
      </div>
      <div class="w-full sm:w-48">
        <label class="block mb-1">Outlet</label>
        <select name="outlet_id" class="w-full border rounded px-3 py-2">
          <option value="">Semua</option>
          @foreach($outlets as $outlet)
            <option value="{{ $outlet->id }}" @selected((string) request('outlet_id')===(string) $outlet->id)>{{ $outlet->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="w-full sm:w-48">
        <label class="block mb-1">Departemen</label>
        <select name="department_id" class="w-full border rounded px-3 py-2">
          <option value="">Semua</option>
          @foreach($departments as $department)
            <option value="{{ $department->id }}" @selected((string) request('department_id')===(string) $department->id)>{{ $department->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="w-full sm:w-64">
        <label class="block mb-1">Karyawan</label>
        <input type="hidden" name="employee_id" id="employeeIdFilter" value="{{ request('employee_id') }}">
        <input type="text" id="employeeSearch" list="karyawan-list" value="{{ $selectedEmployeeLabel }}" class="w-full border rounded px-3 py-2" placeholder="Ketik nama karyawan">
        <datalist id="karyawan-list">
          @foreach($employees as $employee)
            <option data-id="{{ $employee->id }}" value="{{ trim(($employee->employee_number ? $employee->employee_number.' - ' : '').$employee->full_name) }}"></option>
          @endforeach
        </datalist>
      </div>
      <div class="flex w-full items-end gap-2 sm:w-auto">
        <button class="rounded bg-indigo-600 px-4 py-2 text-white">Terapkan</button>
        <a href="{{ route('hrd.attendance.index') }}" class="rounded border px-4 py-2">Reset</a>
      </div>
    </div>
  </form>

  <div class="grid md:grid-cols-6 gap-3 text-sm">
    <div class="card p-3"><div class="text-slate-500">Kehadiran</div><div class="font-semibold">{{ $summary['kehadiran_hari'] }} hari</div></div>
    <div class="card p-3"><div class="text-slate-500">Total Jam Kerja</div><div class="font-semibold">{{ sprintf('%02d:%02d', intdiv($summary['total_jam_kerja_menit'],60), $summary['total_jam_kerja_menit'] % 60) }}</div></div>
    <div class="card p-3"><div class="text-slate-500">Terlambat</div><div class="font-semibold">{{ $summary['terlambat_kali'] }}x / {{ $summary['terlambat_menit'] }}m</div></div>
    <div class="card p-3"><div class="text-slate-500">Pulang Awal</div><div class="font-semibold">{{ $summary['pulang_awal_menit'] }} menit</div></div>
    <div class="card p-3"><div class="text-slate-500">OT</div><div class="font-semibold">{{ $summary['overtime_menit'] }} menit</div></div>
    <div class="card p-3"><div class="text-slate-500">Libur/Cuti</div><div class="font-semibold">{{ $summary['libur_hari'] }}/{{ $summary['cuti_hari'] }}</div></div>
  </div>

  <div class="card p-4 overflow-auto">
    <table class="min-w-full text-sm border-collapse">
      <thead>
        <tr class="bg-slate-100 text-left">
          <th class="p-2 border">TGL</th>
          <th class="p-2 border">OUTLET</th>
          <th class="p-2 border">NAMA</th>
          <th class="p-2 border">SHIFT</th>
          <th class="p-2 border">IN</th>
          <th class="p-2 border">OUT</th>
          <th class="p-2 border">SCAN DATA</th>
          <th class="p-2 border">KURANG</th>
          <th class="p-2 border">KET</th>
          <th class="p-2 border">J.KRJ</th>
          <th class="p-2 border">OT</th>
          <th class="p-2 border">SCAN LOC</th>
          <th class="p-2 border">DETAIL</th>
        </tr>
      </thead>
      <tbody>
        @forelse($sessions as $row)
          @php
            $tz = $row->outlet?->timezone ?: 'Asia/Jakarta';
            $in = $row->first_in_at_utc ? $row->first_in_at_utc->setTimezone($tz)->format('H:i') : '-';
            $out = $row->last_out_at_utc ? $row->last_out_at_utc->setTimezone($tz)->format('H:i') : '-';
            $scheduledIn = $row->scheduled_in_local ?: null;
            $scheduledOut = $row->scheduled_out_local ?: null;
            $scheduledMinutes = ($scheduledIn && $scheduledOut) ? \Carbon\Carbon::parse($scheduledIn)->diffInMinutes(\Carbon\Carbon::parse($scheduledOut), false) : 0;
            $kurang = max(0, (int) $scheduledMinutes - (int) $row->total_work_minutes);
            $scanData = data_get($row->summary_json, 'scan_data', '-');
            $outsideCount = $row->scans->where('is_within_geofence', false)->count();
            $scanLoc = $outsideCount > 0 ? 'OUT('.$outsideCount.')' : 'OK';
          @endphp
          <tr>
            <td class="p-2 border">{{ $row->work_date?->format('d-m-Y') }}</td>
            <td class="p-2 border">{{ $row->outlet?->name ?? '-' }}</td>
            <td class="p-2 border">{{ $row->user?->employee?->full_name ?? $row->user?->name ?? '-' }}</td>
            <td class="p-2 border">{{ $row->shift_code ?? '-' }}</td>
            <td class="p-2 border">{{ $in }}</td>
            <td class="p-2 border">{{ $out }}</td>
            <td class="p-2 border">{{ $scanData }}</td>
            <td class="p-2 border">{{ $kurang }}</td>
            <td class="p-2 border">{{ strtoupper($row->status) }}</td>
            <td class="p-2 border">{{ sprintf('%02d:%02d', intdiv((int) $row->total_work_minutes,60), ((int) $row->total_work_minutes % 60)) }}</td>
            <td class="p-2 border">{{ (int) $row->overtime_minutes }}</td>
            <td class="p-2 border">{{ $scanLoc }}</td>
            <td class="p-2 border">
              <details>
                <summary class="cursor-pointer text-indigo-600">Scan Log</summary>
                <ul class="mt-1 text-xs space-y-1">
                  @forelse($row->scans->sortBy('scanned_at_utc') as $scan)
                    <li>
                      {{ strtoupper($scan->scan_type) }} {{ \Carbon\Carbon::parse($scan->scanned_at_utc, 'UTC')->setTimezone($tz)->format('H:i:s') }}
                      | D={{ (int) ($scan->distance_meters ?? 0) }}m
                      | {{ $scan->is_within_geofence ? 'IN' : 'OUT' }}
                    </li>
                  @empty
                    <li>Tidak ada scan.</li>
                  @endforelse
                </ul>
              </details>
            </td>
          </tr>
        @empty
          <tr><td class="p-2 border text-center text-slate-500" colspan="13">Data presensi belum tersedia.</td></tr>
        @endforelse
      </tbody>
    </table>

    <div class="mt-4">{{ $sessions->links() }}</div>
  </div>
</div>

<div id="tourHrdModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-lg max-w-lg w-full p-5 space-y-3">
    <h3 class="text-lg font-semibold">Panduan Rekap Presensi</h3>
    <ol class="list-decimal ml-5 text-sm space-y-1">
      <li>Pilih filter outlet/departemen/karyawan dan rentang tanggal.</li>
      <li>Review kolom IN/OUT, SCAN DATA, KURANG, dan KET.</li>
      <li>Klik <b>Export PDF</b> untuk unduh laporan periode.</li>
    </ol>
    <div class="flex justify-end">
      <button id="tourHrdClose" class="px-3 py-2 rounded bg-slate-900 text-white">Mengerti</button>
    </div>
  </div>
</div>

<script>
(function () {
  const modal = document.getElementById('tourHrdModal');
  const openBtn = document.getElementById('btnTourHrd');
  const closeBtn = document.getElementById('tourHrdClose');
  const key = 'tour_hrd_rekap_seen_v1';

  function openTour() {
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeTour() {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    localStorage.setItem(key, '1');
  }

  const employeeSearch = document.getElementById('employeeSearch');
  const employeeIdFilter = document.getElementById('employeeIdFilter');
  const employeeOptions = Array.from(document.querySelectorAll('#karyawan-list option'));

  function syncEmployeeFilter() {
    if (!employeeSearch || !employeeIdFilter) return;
    const selected = employeeOptions.find((option) => option.value === employeeSearch.value);
    employeeIdFilter.value = selected ? selected.dataset.id : '';
  }

  if (employeeSearch) employeeSearch.addEventListener('input', syncEmployeeFilter);
  if (openBtn) openBtn.addEventListener('click', openTour);
  if (closeBtn) closeBtn.addEventListener('click', closeTour);
  if (!localStorage.getItem(key)) openTour();
})();
</script>
@endsection
