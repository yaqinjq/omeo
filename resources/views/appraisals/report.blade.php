@extends('layouts.app')
@section('title', 'Laporan Appraisal')
@section('content')
<div class="p-4">

    {{-- HEADER --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px; font-weight:800; color:#1e293b;">Laporan Appraisal</h1>
        <p style="color:#64748B; font-size:13px; margin-top:4px;">
            Monitoring dan evaluasi kinerja karyawan
        </p>
    </div>

    @if(!empty($moduleWarning))
    <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:10px;
                padding:12px 16px; font-size:13px; color:#92400e; margin-bottom:20px;">
        {{ $moduleWarning }}
    </div>
    @endif

    {{-- FILTER BAR --}}
    @php
        $exportParams = array_filter(['period_id' => $periodId, 'status' => $status, 'search' => $search]);
        $exportUrl    = route('appraisals.export-report', $exportParams);
        $exportBulkPdfUrl = route('appraisals.export-bulk-pdf', $exportParams);
    @endphp
    <form method="GET"
          style="display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap; align-items:flex-end;">

        <div>
            <label style="font-size:11px; color:#64748B; font-weight:600; display:block; margin-bottom:4px;">PERIODE</label>
            <select name="period_id"
                    style="border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 12px; font-size:13px; outline:none;">
                <option value="">Semua Periode</option>
                @foreach($periods as $p)
                <option value="{{ $p->id }}" {{ $periodId == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label style="font-size:11px; color:#64748B; font-weight:600; display:block; margin-bottom:4px;">STATUS</label>
            <select name="status"
                    style="border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 12px; font-size:13px; outline:none;">
                <option value="">Semua Status</option>
                <option value="draft"     {{ $status === 'draft'     ? 'selected' : '' }}>Draft</option>
                <option value="submitted" {{ $status === 'submitted' ? 'selected' : '' }}>Submitted</option>
                <option value="approved"  {{ $status === 'approved'  ? 'selected' : '' }}>Approved</option>
            </select>
        </div>

        <div>
            <label style="font-size:11px; color:#64748B; font-weight:600; display:block; margin-bottom:4px;">CARI</label>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Nama / jabatan / no. karyawan..."
                   style="border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 14px; font-size:13px; outline:none; width:240px;"
                   onfocus="this.style.borderColor='#7C3AED'" onblur="this.style.borderColor='#E2E8F0'">
        </div>

        <button type="submit"
                style="background:#7C3AED; color:white; border:none; padding:8px 16px;
                       border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
            Terapkan Filter
        </button>

        @if($periodId || $status || $search)
        <a href="{{ route('appraisals.report') }}"
           style="padding:8px 14px; background:#F1F5F9; color:#64748B; border-radius:8px;
                  font-size:13px; font-weight:600; text-decoration:none;">
            Reset
        </a>
        @endif

        <a href="{{ $exportBulkPdfUrl }}"
           style="margin-left:auto; background:#1E3A8A; color:white; padding:8px 16px;
                  border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;
                  display:inline-flex; align-items:center; gap:6px;"
           title="Gabungkan laporan detail semua karyawan sesuai filter ke dalam 1 file PDF">
            &#8659; Export PDF Semua
        </a>

        <a href="{{ $exportUrl }}"
           style="background:#059669; color:white; padding:8px 16px;
                  border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;
                  display:inline-flex; align-items:center; gap:6px;">
            &#8659; Export Excel
        </a>
    </form>

    {{-- SUMMARY CARDS --}}
    <div style="display:grid; gap:12px; margin-bottom:24px; grid-template-columns:repeat(auto-fill,minmax(155px,1fr));">
        @foreach([
            ['label'=>'Total Karyawan',     'value'=>$stats['total_employees'],  'color'=>'#7C3AED','bg'=>'#F3E8FF','icon'=>'👥'],
            ['label'=>'Total Appraisal',    'value'=>$stats['total_appraisals'], 'color'=>'#0369A1','bg'=>'#E0F2FE','icon'=>'📋'],
            ['label'=>'Selesai (Approved)', 'value'=>$stats['approved'],         'color'=>'#059669','bg'=>'#DCFCE7','icon'=>'✅'],
            ['label'=>'Dalam Proses',       'value'=>$stats['submitted'],        'color'=>'#1D4ED8','bg'=>'#DBEAFE','icon'=>'⏳'],
            ['label'=>'Draft',              'value'=>$stats['draft'],            'color'=>'#92400E','bg'=>'#FEF9C3','icon'=>'📝'],
            ['label'=>'Rata-rata Skor',     'value'=>number_format($avgScore ?? 0, 1),'color'=>'#0369A1','bg'=>'#F0FDF4','icon'=>'⭐'],
        ] as $card)
        <div style="background:{{ $card['bg'] }}; border-radius:12px; padding:14px 16px; text-align:center;">
            <div style="font-size:22px; margin-bottom:4px;">{{ $card['icon'] }}</div>
            <div style="font-size:24px; font-weight:800; color:{{ $card['color'] }};">{{ $card['value'] }}</div>
            <div style="font-size:11px; color:{{ $card['color'] }}; opacity:.8; font-weight:600; margin-top:2px;">{{ $card['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- DISTRIBUSI HASIL --}}
    @if($distribution->isNotEmpty())
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; padding:20px; margin-bottom:24px;">
        <h3 style="font-size:14px; font-weight:700; color:#1e293b; margin:0 0 16px 0;">Distribusi Hasil Appraisal</h3>
        @php
            $maxVal = $distribution->max() ?: 1;
            $resultColorMap = array_merge([
                'Lulus'=>'#059669','Perpanjang Probation'=>'#F59E0B','Tidak Lulus'=>'#DC2626',
                'Promosi'=>'#7C3AED','Tetap'=>'#1D4ED8',
            ], array_column(\App\Support\AppraisalGrading::BANDS, 'color', 'label'));
        @endphp
        @foreach($distribution as $result => $count)
        @php $color = $resultColorMap[$result] ?? '#94A3B8'; $pct = round(($count/$maxVal)*100); @endphp
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
            <div style="width:170px; font-size:12px; color:#475569; font-weight:600; flex-shrink:0;">{{ $result }}</div>
            <div style="flex:1; background:#F1F5F9; border-radius:99px; height:24px; position:relative; overflow:hidden;">
                <div style="width:{{ $pct }}%; background:{{ $color }}; height:100%; border-radius:99px; display:flex; align-items:center; padding-left:10px;">
                    @if($pct > 15)<span style="color:white; font-size:11px; font-weight:700;">{{ $count }}</span>@endif
                </div>
                @if($pct <= 15)
                <span style="position:absolute; left:{{ $pct }}%; padding-left:8px; top:50%; transform:translateY(-50%); font-size:11px; font-weight:700; color:{{ $color }};">{{ $count }}</span>
                @endif
            </div>
            <div style="width:40px; text-align:right; font-size:12px; color:#64748B; flex-shrink:0;">{{ $count }}x</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- TABEL DETAIL --}}
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #F1F5F9; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:14px; font-weight:700; color:#1e293b; margin:0;">Detail per Karyawan</h3>
            <span style="font-size:12px; color:#94A3B8;">
                {{ $paginator->total() }} karyawan
                @if($paginator->lastPage() > 1)
                &mdash; halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
                @endif
            </span>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:12px;">
                <thead>
                    <tr style="background:#F8FAFC;">
                        <th style="padding:10px 14px; text-align:left; color:#64748B; font-weight:600; width:44px;">No</th>
                        <th style="padding:10px 14px; text-align:left; color:#64748B; font-weight:600;">Nama Karyawan</th>
                        <th style="padding:10px 14px; text-align:left; color:#64748B; font-weight:600;">Periode</th>
                        <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Evaluator</th>
                        <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Rata-rata &#8595;</th>
                        <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Hasil</th>
                        <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600;">Status</th>
                        <th style="padding:10px 14px; text-align:center; color:#64748B; font-weight:600; width:90px;">Aksi</th>
                    </tr>
                </thead>

                @forelse($paginator as $idx => $row)
                @php
                    $avgS = $row->avg_score;
                    $scoreStyle = ($avgS === null)
                        ? 'background:#F1F5F9;color:#94A3B8;'
                        : (floatval($avgS) >= 80 ? 'background:#DCFCE7;color:#166534;'
                        : (floatval($avgS) >= 60 ? 'background:#FEF9C3;color:#854D0E;'
                        : 'background:#FEE2E2;color:#991B1B;'));

                    $badgeColors = [
                        'Lulus'=>'background:#DCFCE7;color:#166534;','Promosi'=>'background:#F3E8FF;color:#7C3AED;',
                        'Perpanjang Probation'=>'background:#FEF9C3;color:#854D0E;','Tidak Lulus'=>'background:#FEE2E2;color:#991B1B;',
                        'Tetap'=>'background:#DBEAFE;color:#1D4ED8;',
                    ];
                    $gradeBand = \App\Support\AppraisalGrading::band($row->latest_result);
                    $resultStyle = $badgeColors[$row->latest_result]
                        ?? ($gradeBand ? "background:{$gradeBand['bg']};color:{$gradeBand['color']};" : 'background:#F1F5F9;color:#64748B;');

                    if ($row->approved_count > 0) {
                        $statusStyle = 'background:#DCFCE7;color:#166534;';
                        $statusLabel = $row->approved_count . ' approved';
                    } elseif ($row->submitted_count > 0) {
                        $statusStyle = 'background:#DBEAFE;color:#1D4ED8;';
                        $statusLabel = $row->submitted_count . ' submitted';
                    } else {
                        $statusStyle = 'background:#FEF9C3;color:#854D0E;';
                        $statusLabel = $row->draft_count . ' draft';
                    }

                    $rowNum = ($paginator->currentPage() - 1) * $paginator->perPage() + $idx + 1;
                    $rowBg  = $idx % 2 === 1 ? 'background:#FAFAFA;' : '';

                    $detailUrl = route('appraisals.report-employee', array_filter([
                        'employeeId' => $row->employee_id,
                        'period_id'  => $periodId,
                    ]));
                    $pdfUrl = route('appraisals.export-employee-pdf', array_filter([
                        'employeeId' => $row->employee_id,
                        'period_id'  => $periodId,
                    ]));
                @endphp
                <tbody>
                    <tr style="{{ $rowBg }} border-bottom:1px solid #F1F5F9;">

                        <td style="padding:12px 14px; color:#94A3B8;">{{ $rowNum }}</td>

                        <td style="padding:12px 14px;">
                            <div style="font-weight:700; color:#1e293b; font-size:13px;">
                                {{ $row->employee_name }}
                                @if(!empty($row->is_legacy))
                                <span style="background:#F3E8FF;color:#7C3AED;padding:1px 6px;border-radius:99px;font-size:9px;font-weight:700;margin-left:4px;vertical-align:middle;">&#128194; Historis MEO</span>
                                @endif
                            </div>
                            <div style="font-size:11px; color:#94A3B8; margin-top:1px;">
                                {{ ($row->employee_number !== '-' ? $row->employee_number . ' · ' : '') . ($row->jabatan ?: '-') }}
                            </div>
                        </td>

                        <td style="padding:12px 14px; font-size:12px; color:#475569;">{{ $row->period_name }}</td>

                        <td style="padding:12px 14px; text-align:center;">
                            <span style="background:#F3E8FF; color:#7C3AED; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:600;">
                                {{ $row->evaluator_count }}
                            </span>
                        </td>

                        <td style="padding:12px 14px; text-align:center;">
                            <span style="{{ $scoreStyle }} padding:4px 10px; border-radius:8px; font-size:13px; font-weight:800;">
                                {{ $avgS !== null ? number_format(floatval($avgS), 1) : '—' }}
                            </span>
                        </td>

                        <td style="padding:12px 14px; text-align:center;">
                            <span style="{{ $resultStyle }} padding:3px 10px; border-radius:99px; font-size:11px; font-weight:600;">
                                {{ $row->latest_result ?: '—' }}
                            </span>
                        </td>

                        <td style="padding:12px 14px; text-align:center;">
                            <span style="{{ $statusStyle }} padding:3px 10px; border-radius:99px; font-size:11px; font-weight:600;">
                                {{ $statusLabel }}
                            </span>
                        </td>

                        <td style="padding:12px 14px; text-align:center;">
                            <div style="display:flex; gap:6px; justify-content:center; align-items:center;">
                                <a href="{{ $detailUrl }}" title="Lihat Detail"
                                   style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:7px; background:#EFF6FF; color:#1D4ED8; text-decoration:none; font-size:15px; border:1px solid #BFDBFE;">
                                    &#128269;
                                </a>
                                <a href="{{ $pdfUrl }}" title="Export PDF Karyawan"
                                   style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:7px; background:#FEF2F2; color:#DC2626; text-decoration:none; font-size:15px; border:1px solid #FECACA;">
                                    &#x2B07;
                                </a>
                            </div>
                        </td>

                    </tr>
                </tbody>
                @empty
                <tbody>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:48px; color:#94A3B8;">
                            <div style="font-size:40px; margin-bottom:12px;">📋</div>
                            <div style="font-size:14px; font-weight:600;">Tidak ada data appraisal</div>
                        </td>
                    </tr>
                </tbody>
                @endforelse
            </table>
        </div>

        @if($paginator->lastPage() > 1)
        <div style="padding:16px 20px; border-top:1px solid #F1F5F9;">
            {!! $paginator->links() !!}
        </div>
        @endif
    </div>

</div>
@endsection
