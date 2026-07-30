<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Report Absen</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
    h1 { margin: 0; font-size: 14px; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #cbd5e1; padding: 4px; text-align: left; vertical-align: top; }
    th { background: #f1f5f9; }
    .summary td { border: 1px solid #cbd5e1; padding: 6px; }
    .section { margin-top: 10px; }
    .employee-block { page-break-after: always; }
    .employee-block:last-child { page-break-after: auto; }
  </style>
</head>
<body>
  <div class="muted">Generated: {{ $generatedAt->format('d-m-Y H:i') }}</div>

  @if($employeeGroups->count() > 1)
    <div class="section">
      <b>REKAP {{ $employeeGroups->count() }} KARYAWAN — PERIODE {{ $fromDate->format('d-m-Y') }} s/d {{ $toDate->format('d-m-Y') }}</b>
    </div>
  @endif

  @forelse($employeeGroups as $group)
    <div class="employee-block">
      <h1>REPORT ABSEN {{ strtoupper($group['employeeName']) }} - [{{ $group['employeeNo'] }}] PERIODE {{ $fromDate->format('d-m-Y') }} s/d {{ $toDate->format('d-m-Y') }}</h1>

      <table>
        <thead>
          <tr>
            <th>TGL</th>
            <th>OUTLET</th>
            <th>SHIFT</th>
            <th>IN</th>
            <th>OUT</th>
            <th>SCAN DATA</th>
            <th>KURANG</th>
            <th>KET</th>
            <th>J.KRJ</th>
            <th>OT</th>
            <th>DOC</th>
            <th>SCAN LOC</th>
          </tr>
        </thead>
        <tbody>
          @foreach($group['rows'] as $row)
            @php
              $tz = $row->outlet?->timezone ?: 'Asia/Jakarta';
              $in = $row->first_in_at_utc ? $row->first_in_at_utc->setTimezone($tz)->format('H:i') : '-';
              $out = $row->last_out_at_utc ? $row->last_out_at_utc->setTimezone($tz)->format('H:i') : '-';
              $scheduledMinutes = 0;
              if ($row->scheduled_in_local && $row->scheduled_out_local) {
                $scheduledMinutes = \Carbon\Carbon::parse($row->scheduled_in_local)->diffInMinutes(\Carbon\Carbon::parse($row->scheduled_out_local), false);
              }
              $kurang = max(0, (int) $scheduledMinutes - (int) $row->total_work_minutes);
              $scanData = data_get($row->summary_json, 'scan_data', '-');
              $outsideCount = $row->scans->where('is_within_geofence', false)->count();
              $scanLoc = $outsideCount > 0 ? 'OUT('.$outsideCount.')' : 'OK';
              $doc = '-';
            @endphp
            <tr>
              <td>{{ $row->work_date?->format('d-m-Y') }}</td>
              <td>{{ $row->outlet?->name ?? '-' }}</td>
              <td>{{ $row->shift_code ?? '-' }}</td>
              <td>{{ $in }}</td>
              <td>{{ $out }}</td>
              <td>{{ $scanData }}</td>
              <td>{{ $kurang }}</td>
              <td>{{ strtoupper($row->status) }}</td>
              <td>{{ sprintf('%02d:%02d', intdiv((int) $row->total_work_minutes, 60), ((int) $row->total_work_minutes % 60)) }}</td>
              <td>{{ (int) $row->overtime_minutes }}</td>
              <td>{{ $doc }}</td>
              <td>{{ $scanLoc }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div class="section">
        <table class="summary">
          <tr>
            <td>KEHADIRAN: <b>{{ $group['summary']['kehadiran_hari'] }}</b> hari</td>
            <td>TOTAL JAM KERJA: <b>{{ sprintf('%02d:%02d', intdiv($group['summary']['total_jam_kerja_menit'],60), $group['summary']['total_jam_kerja_menit'] % 60) }}</b></td>
            <td>KETERLAMBATAN: <b>{{ $group['summary']['terlambat_kali'] }}x</b> / <b>{{ $group['summary']['terlambat_menit'] }} menit</b></td>
          </tr>
          <tr>
            <td>LIBUR: <b>{{ $group['summary']['libur_hari'] }}</b></td>
            <td>CUTI: <b>{{ $group['summary']['cuti_hari'] }}</b></td>
            <td>INCOMPLETE/ABSENT: <b>{{ $group['summary']['incomplete_hari'] }}/{{ $group['summary']['absent_hari'] }}</b></td>
          </tr>
        </table>
      </div>
    </div>
  @empty
    <div style="text-align:center;">Tidak ada data.</div>
  @endforelse

  <div class="section">
    <b>Legend:</b>
    J.KRJ = Jam Kerja, OT = Overtime, KET = Status hari, KURANG = kekurangan menit terhadap jadwal,
    SCAN DATA = daftar jam scan (lokal outlet), SCAN LOC = validitas geofence scan.
  </div>
</body>
</html>
