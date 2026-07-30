<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Appraisal #{{ $appraisal->id }}</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #0f172a; }
    h1, h2, h3 { margin: 0 0 8px; }
    .meta { margin-bottom: 14px; line-height: 1.6; }
    .card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
    th { background: #f8fafc; text-align: left; }
    .grid td { width: 50%; }
  </style>
</head>
<body>
  <h1>Report Appraisal Probation</h1>
  <div class="meta">
    <strong>Karyawan:</strong> {{ $appraisal->employee?->full_name }} ({{ $appraisal->employee?->nik }})<br>
    <strong>Grouping legacy:</strong> {{ $appraisal->period?->name ?? '-' }}<br>
    <strong>Trigger:</strong> {{ strtoupper(str_replace('_', ' ', $appraisal->trigger_source ?? 'legacy')) }}<br>
    <strong>Probation snapshot:</strong> {{ $appraisal->probation_end_snapshot?->format('d-m-Y') ?? '-' }}<br>
    <strong>Evaluator:</strong> {{ $appraisal->appraiser?->name ?? '-' }}<br>
    <strong>Tanggal appraisal:</strong> {{ optional($appraisal->date_appraised)->format('d-m-Y') }}<br>
    <strong>Status:</strong> {{ strtoupper($appraisal->status ?? '-') }}<br>
  </div>

  <div class="card">
    <h3>Ringkasan Hasil</h3>
    <table class="grid">
      <tr>
        <td><strong>Nilai Akhir</strong><br>{{ $appraisal->final_score ?? '-' }}</td>
        <td><strong>Final Result</strong><br>{{ $appraisal->final_result ?? '-' }}</td>
      </tr>
      <tr>
        <td><strong>Due Evaluator</strong><br>{{ $appraisal->due_date?->format('d-m-Y') ?? '-' }}</td>
        <td><strong>Alasan Trigger</strong><br>{{ $appraisal->trigger_reason ?: '-' }}</td>
      </tr>
    </table>
  </div>

  <div class="card">
    <h3>Ringkasan Komponen Penilaian</h3>
    <table>
      <thead>
        <tr>
          <th>Komponen</th>
          <th>Bobot</th>
          <th>Score</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody>
        @foreach($appraisal->componentScores as $component)
          <tr>
            <td>{{ $component->component_label }}</td>
            <td>{{ $component->weight }}</td>
            <td>{{ $component->score_normalized ?? '-' }}</td>
            <td>{{ $component->notes ?? '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>Feedback Evaluator</h3>
    <table class="grid">
      <tr>
        <td><strong>Saran</strong><br>{{ $appraisal->feedback_strengths ?: '-' }}</td>
        <td><strong>Area Perbaikan</strong><br>{{ $appraisal->feedback_improvements ?: '-' }}</td>
      </tr>
      <tr>
        <td colspan="2"><strong>Catatan Lain</strong><br>{{ $appraisal->feedback_notes ?: '-' }}</td>
      </tr>
    </table>
  </div>

  <div class="card">
    <h3>Detail Kriteria Penilaian</h3>
    <table>
      <thead>
        <tr>
          <th>Kategori</th>
          <th>Pertanyaan</th>
          <th>Bobot</th>
          <th>Score</th>
          <th>Komentar</th>
        </tr>
      </thead>
      <tbody>
        @foreach($appraisal->details as $d)
          <tr>
            <td>{{ $d->indicator?->category }}</td>
            <td>{{ $d->indicator?->question }}</td>
            <td>{{ $d->indicator?->weight }}</td>
            <td>{{ $d->score }}</td>
            <td>{{ $d->comment }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</body>
</html>
