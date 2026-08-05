{{-- ══ HEADER ══ --}}
<table class="hdr">
<tr>
    <td>
        <div class="app-name">{{ $appName }}</div>
        <div class="app-sub">Management Employee Organization</div>
    </td>
    <td class="hdr-right">
        Dokumen Appraisal Karyawan<br>
        {{ now()->format('d M Y') }}
    </td>
</tr>
</table>

{{-- ══ TITLE ══ --}}
<h1>Laporan Appraisal Detail Per Karyawan</h1>
<div class="doc-meta">
    Laporan Appraisal Karyawan<br>
    Periode {{ $periodName }} | Dokumen ini memuat appraisal karyawan sesuai range tanggal: Semua tanggal appraisal.
</div>

{{-- ══ INFO KARYAWAN ══ --}}
<div class="sec-title">Informasi Appraisal Karyawan</div>
<table class="info-tbl">
<tr>
    <td class="lbl">Nama Karyawan</td>
    <td class="val">{{ strtoupper($employee->full_name) }}</td>
    <td class="lbl">ID Karyawan</td>
    <td class="val">{{ $employee->id }}</td>
</tr>
<tr>
    <td class="lbl">ID Training</td>
    <td class="val">{{ $employee->id }}</td>
    <td class="lbl">Label Periode</td>
    <td class="val">{{ $periodName }}</td>
</tr>
<tr>
    <td class="lbl">No. Komputer</td>
    <td class="val">{{ $employee->nokom ?? $employee->employee_number ?? '-' }}</td>
    <td class="lbl">Range Filter</td>
    <td class="val">Semua tanggal appraisal</td>
</tr>
<tr>
    <td class="lbl">Departemen</td>
    <td class="val">{{ strtoupper($employee->department?->name ?? '-') }}</td>
    <td class="lbl">Tanggal Dinilai</td>
    <td class="val">{{ $dateRange }}</td>
</tr>
<tr>
    <td class="lbl">Jabatan</td>
    <td class="val">{{ strtoupper($employee->jabatan ?? '-') }}</td>
    <td class="lbl">Tanggal Join</td>
    <td class="val">{{ $employee->join_date?->format('Y-m-d') ?? '-' }}</td>
</tr>
<tr>
    <td class="lbl">Jumlah Evaluator</td>
    <td class="val">{{ $evaluatorCount }}</td>
    <td class="lbl">Rata-rata Akhir</td>
    <td class="val">{{ $overallAvg !== null ? number_format($overallAvg, 2) . ' (' . $overallGrade . ')' : '-' }}</td>
</tr>
<tr>
    <td class="lbl">Nama Evaluator Range</td>
    <td colspan="3" style="font-size:8px; color:#374151;">{{ $evaluatorNames }}</td>
</tr>
<tr>
    <td class="lbl">Status Signature Batch Terbaru</td>
    <td colspan="3" class="val">{{ $approvedCount }} / {{ $evaluatorCount }} slot</td>
</tr>
</table>

{{-- ══ MATRIKS PENILAIAN ══ --}}
@if(count($matrix) > 0)
@php $chunks = $appraisals->chunk(8); $totalChunks = $chunks->count(); @endphp
@foreach($chunks as $chunkIndex => $chunk)

<div class="sec-title" style="margin-top:12px;">Matriks Penilaian Sesuai Filter</div>
<div class="part-hdr">Bagian {{ $chunkIndex + 1 }} dari {{ $totalChunks }} ({{ $chunk->count() }} evaluator)</div>

<table class="mx-tbl">
<thead>
<tr>
    <th class="crit-hdr" style="width:140px;">Kriteria Penilaian</th>
    @foreach($chunk as $a)
    <th>{{ $showNames[$a->id] ?? ('Evaluator '.$evaluatorNumber[$a->id]) }}</th>
    @endforeach
    <th>Rata-rata</th>
</tr>
</thead>
<tbody>
@foreach($matrix as $mRow)
@php
    $rowAvg = $mRow['avg'];
    $scoreClass = $rowAvg !== null
        ? ($rowAvg >= 3.5 ? 'score-hi' : ($rowAvg < 2.5 ? 'score-lo' : ''))
        : '';
@endphp
<tr>
    <td class="crit-lbl">{{ $mRow['label'] }}</td>
    @foreach($chunk as $a)
    @php $s = $mRow['scores'][$a->id] ?? null; @endphp
    <td>{{ $s !== null ? number_format($s, 2) : '-' }}</td>
    @endforeach
    <td class="{{ $scoreClass }}" style="font-weight:bold;">
        {{ $rowAvg !== null ? number_format($rowAvg, 2) : '-' }}
    </td>
</tr>
@endforeach
<tr class="avg-row">
    <td class="crit-lbl" style="font-weight:bold;">Rata-rata Per Evaluator</td>
    @foreach($chunk as $a)
    @php $ea = $evalAvgs[$a->id] ?? null; @endphp
    <td style="{{ $ea !== null && $ea >= 3.5 ? 'color:#065f46;' : ($ea !== null && $ea < 2.5 ? 'color:#991b1b;' : '') }}">
        {{ $ea !== null ? number_format($ea, 2) : '-' }}
    </td>
    @endforeach
    <td>{{ $matrixOverallAvg !== null ? number_format($matrixOverallAvg, 2) : '-' }}</td>
</tr>
</tbody>
</table>

@if(! $loop->last)
<div class="page-break"></div>
@endif

@endforeach
@endif

{{-- ══ RINGKASAN NARASI ══ --}}
<div class="page-break"></div>
<div class="sec-title">Ringkasan Narasi dan Feedback Sesuai Filter</div>
<table class="narr-tbl">
<thead>
<tr>
    <th style="width:60px;">Evaluator</th>
    <th style="width:60px;">Tanggal</th>
    <th style="width:80px;">Proposed Status</th>
    <th>Saran</th>
    <th>Kritik / Area Perbaikan</th>
    <th>Catatan Lain</th>
</tr>
</thead>
<tbody>
@foreach($narratives as $n)
<tr>
    <td>{{ $n['name'] ?? ('Evaluator '.$n['no']) }}</td>
    <td>{{ $n['date'] }}</td>
    <td>{{ $n['proposed_status'] }}</td>
    <td>{{ $n['strengths'] ?? '-' }}</td>
    <td>{{ $n['improvements'] ?? '-' }}</td>
    <td>{{ $n['notes'] ?? '-' }}</td>
</tr>
@endforeach
</tbody>
</table>

{{-- ══ KEPUTUSAN DAN PENGESAHAN ══ --}}
<div class="page-break"></div>
<div class="sec-title">Keputusan dan Pengesahan</div>
<table class="dec-tbl">
<tr>
    <td class="lbl">Tanda Tangan Karyawan</td>
    <td colspan="3">
        <div style="height:20px;"></div>
        <div style="font-size:8px; color:#6b7280;">Belum ditandatangani</div>
        <div style="font-size:8px; color:#6b7280; margin-top:2px;">Nama signer:</div>
    </td>
</tr>
<tr>
    <td class="lbl">Proposed To Be</td>
    <td colspan="3">
        @php
            $proposedOptions = [
                'RE CONTRACT'                     => 're_contract',
                'Discontinue the Contract'        => 'discontinue',
                'Promoted'                        => 'promoted',
                'Confirmation of Permanent Worker'=> 'permanent',
            ];
            $selectedLabel = $consensusStatus;
        @endphp
        @foreach($proposedOptions as $label => $key)
        @php $isSelected = strtolower($selectedLabel) === strtolower($label) || $selectedLabel === strtoupper($label); @endphp
        <div class="proposed-item {{ $isSelected ? 'proposed-sel' : 'proposed-off' }}">
            {{ $isSelected ? '[X]' : '[ ]' }} {{ $label }}
        </div>
        @endforeach
    </td>
</tr>
<tr>
    <td class="lbl">Position Proposed</td>
    <td style="width:30%;">{{ $latestAppraisal->proposed_position ?? '' }}</td>
    <td class="lbl">Salary Increased</td>
    <td></td>
</tr>
<tr>
    <td class="lbl">Effective Date</td>
    <td></td>
    <td class="lbl">Masa Kontrak Diperpanjang</td>
    <td style="font-size:8px; color:#374151;">
        @php $dur = $latestAppraisal->proposed_contract_duration ?? null; @endphp
        <div class="{{ $dur === '6_bulan' || $dur === '6 Bulan' ? 'proposed-sel' : 'proposed-off' }}">
            {{ ($dur === '6_bulan' || $dur === '6 Bulan') ? '[X]' : '[ ]' }} 6 Bulan
        </div>
        <div class="{{ $dur === '1_tahun' || $dur === '1 Tahun' ? 'proposed-sel' : 'proposed-off' }}">
            {{ ($dur === '1_tahun' || $dur === '1 Tahun') ? '[X]' : '[ ]' }} 1 Tahun
        </div>
        <div class="{{ $dur === '2_tahun' || $dur === '2 Tahun' ? 'proposed-sel' : 'proposed-off' }}">
            {{ ($dur === '2_tahun' || $dur === '2 Tahun') ? '[X]' : '[ ]' }} 2 Tahun
        </div>
    </td>
</tr>
</table>

<div class="sec-title" style="margin-top:14px;">Persetujuan Pimpinan</div>
<table class="appr-tbl">
<tr>
    <td>
        <div class="appr-lbl">HRD / Super Administrator</div>
        <div class="appr-space"></div>
    </td>
    <td>
        <div class="appr-lbl">Supervisor / PIC</div>
        <div class="appr-space"></div>
    </td>
    <td>
        <div class="appr-lbl">Manager / ASPV / ASM</div>
        <div class="appr-space"></div>
    </td>
    <td>
        <div class="appr-lbl">Managing Director / Manager</div>
        <div class="appr-space"></div>
    </td>
</tr>
</table>

<div class="footer">
    Dokumen Appraisal Karyawan &mdash; {{ now()->format('d\t\h F, Y H:i') }}
</div>
