@extends('layouts.app')
@section('title', 'Detail Appraisal - ' . $employee->full_name)
@section('content')

{{-- flatpickr CDN --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
{{-- signature_pad CDN --}}
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

<div class="p-4" x-data="sigModal()">

    {{-- ── HEADER ── --}}
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
        <a href="{{ route('appraisals.report', array_filter(['period_id'=>$periodId])) }}"
           style="display:inline-flex; align-items:center; gap:6px; padding:7px 14px; background:#F1F5F9; color:#475569;
                  border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid #E2E8F0;">
            &#8592; Kembali
        </a>
        <div>
            <h1 style="font-size:20px; font-weight:800; color:#1e293b; margin:0;">
                Detail Appraisal Karyawan
                @if($appraisals->contains(fn($a) => $a->migration_source === 'meo_legacy'))
                <span style="background:#F3E8FF;color:#7C3AED;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:700;vertical-align:middle;margin-left:6px;">&#128194; Historis MEO</span>
                @endif
            </h1>
            <p style="color:#64748B; font-size:12px; margin:2px 0 0;">
                {{ $employee->full_name }} &mdash; {{ $periodName }}
            </p>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#DCFCE7; border:1px solid #BBF7D0; border-radius:8px; padding:10px 16px; font-size:13px; color:#166534; margin-bottom:16px;">
        &#10003; {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="background:#FEE2E2; border:1px solid #FECACA; border-radius:8px; padding:10px 16px; font-size:13px; color:#991B1B; margin-bottom:16px;">
        @foreach($errors->all() as $err) <div>&#x26A0; {{ $err }}</div> @endforeach
    </div>
    @endif

    @if(in_array((string) auth()->user()->role, ['admin','hrd'], true) && $pendingEditRequests->isNotEmpty())
    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:14px 18px; margin-bottom:16px;">
        <div style="font-size:13px; font-weight:700; color:#92400E; margin-bottom:8px;">&#9203; Permintaan Edit Penilaian Menunggu Persetujuan</div>
        @foreach($pendingEditRequests as $appraisalId => $req)
            @php $evalNum = $evaluatorNumber[$appraisalId] ?? '?'; @endphp
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 0; border-top:1px solid #FEF3C7;">
                <div style="font-size:12px; color:#78350F;">
                    <b>Evaluator {{ $evalNum }}</b> ({{ $req->requestedBy?->name ?? '-' }}) &mdash; {{ $req->reason }}
                    <span style="color:#B45309;">&middot; {{ $req->created_at->format('d-m-Y H:i') }}</span>
                </div>
                <div style="display:flex; gap:6px; flex-shrink:0;">
                    <form method="POST" action="{{ route('appraisal-edit-requests.approve', $req) }}">
                        @csrf
                        <button type="submit" style="font-size:11px; background:#059669; color:white; border:none; padding:5px 12px; border-radius:6px; cursor:pointer;">Setujui</button>
                    </form>
                    <form method="POST" action="{{ route('appraisal-edit-requests.reject', $req) }}">
                        @csrf
                        <button type="submit" style="font-size:11px; background:#DC2626; color:white; border:none; padding:5px 12px; border-radius:6px; cursor:pointer;">Tolak</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    @endif

    @if(in_array((string) auth()->user()->role, ['admin','hrd'], true))
    <details style="background:white; border:1px solid #E2E8F0; border-radius:10px; padding:12px 18px; margin-bottom:16px;">
        <summary style="cursor:pointer; font-size:13px; font-weight:600; color:#1D4ED8;">Perpanjang Due Date — Semua Evaluator</summary>
        <form method="POST" action="{{ route('appraisals.report-employee.extend-due-date-bulk', $employee->id) }}" style="margin-top:10px; max-width:420px; display:flex; flex-direction:column; gap:8px;">
            @csrf
            <div>
                <label style="display:block; font-size:11px; color:#64748B; margin-bottom:3px;">Due date baru (berlaku untuk semua evaluator yang belum approved)</label>
                <input type="date" name="new_due_date" required style="width:100%; border:1px solid #CBD5E1; border-radius:6px; padding:6px 8px; font-size:13px;">
            </div>
            <div>
                <label style="display:block; font-size:11px; color:#64748B; margin-bottom:3px;">Alasan</label>
                <textarea name="reason" required rows="2" style="width:100%; border:1px solid #CBD5E1; border-radius:6px; padding:6px 8px; font-size:13px;"></textarea>
            </div>
            <button type="submit" style="font-size:12px; background:#1D4ED8; color:white; border:none; padding:7px 14px; border-radius:6px; cursor:pointer; align-self:flex-start;">Simpan untuk Semua Evaluator</button>
        </form>
    </details>
    @endif

    @if($pendingAppraisals->isNotEmpty())
    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:14px 18px; margin-bottom:16px;">
        <div style="font-size:13px; font-weight:700; color:#92400E; margin-bottom:8px;">&#8987; Evaluator Belum Mengisi ({{ $pendingAppraisals->count() }})</div>
        @foreach($pendingAppraisals as $pa)
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:8px 0; border-top:1px solid #FEF3C7;">
                <div style="font-size:12px; color:#78350F;">
                    <b>{{ $pa->appraiser?->name ?? 'Evaluator' }}</b>
                    @if($pa->due_date)
                        <span style="color:#B45309;">&middot; due date {{ $pa->due_date->format('d-m-Y') }}</span>
                    @endif
                    @if($pa->last_reminded_at)
                        <span style="color:#B45309;">&middot; terakhir di-remind {{ $pa->last_reminded_at->format('d-m-Y H:i') }}</span>
                    @endif
                </div>
                @if(in_array((string) auth()->user()->role, ['admin','hrd'], true))
                    @php
                        $paCooldownUntil = $pa->last_reminded_at
                            ? $pa->last_reminded_at->copy()->addHours(\App\Http\Controllers\AppraisalController::REMINDER_COOLDOWN_HOURS)
                            : null;
                        $paOnCooldown = $paCooldownUntil && $paCooldownUntil->isFuture();
                    @endphp
                    <div style="display:flex; gap:6px; flex-shrink:0;">
                        <form method="POST" action="{{ route('appraisals.remind', $pa) }}" title="{{ $paOnCooldown ? 'Bisa dikirim ulang mulai '.$paCooldownUntil->format('d M Y H:i') : '' }}">
                            @csrf
                            <button type="submit" style="font-size:11px; background:{{ $paOnCooldown ? '#CBD5E1' : '#D97706' }}; color:white; border:none; padding:5px 12px; border-radius:6px; cursor:{{ $paOnCooldown ? 'not-allowed' : 'pointer' }};" @disabled($paOnCooldown)>Kirim Reminder</button>
                        </form>
                        <form method="POST" action="{{ route('appraisals.remove-evaluator', $pa) }}"
                              onsubmit="return confirm('Hapus undangan evaluator {{ addslashes($pa->appraiser?->name ?? 'ini') }} untuk karyawan ini? Belum ada penilaian yang diisi, jadi tidak ada data yang hilang.');">
                            @csrf
                            <button type="submit" style="font-size:11px; background:white; color:#DC2626; border:1px solid #FCA5A5; padding:5px 12px; border-radius:6px; cursor:pointer;">Hapus</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    {{-- ── INFO BAR ── --}}
    @php
        $sigCount    = $sigBatch?->signedCount() ?? 0;
        $sigSlotTotal = $sigBatch?->slots->count() ?? 0;
        $evalNames  = $appraisals->map(fn($a) => $a->appraiser?->name ?? 'Evaluator')->join(', ');
        $chunks     = $appraisals->chunk(8);
        $chunkCount = $chunks->count();
    @endphp
    <div style="background:#EFF6FF; border-left:4px solid #2563EB; border-radius:10px; padding:14px 18px; margin-bottom:20px; display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start;">
        <div style="flex:1; min-width:250px;">
            <div style="font-size:13px; font-weight:700; color:#1e3a8a; margin-bottom:6px;">
                Report Karyawan &mdash; {{ $periodName }}
            </div>
            <div style="font-size:12px; color:#1e40af; line-height:1.8;">
                <b>Karyawan:</b> {{ $employee->full_name }} ({{ $employee->employee_number ?? '#'.$employee->id }})<br>
                <b>Range Filter:</b> {{ $dateFrom ?? 'Semua' }} {{ ($dateFrom && $dateTo) ? 's/d ' . $dateTo : '' }}<br>
                @if($dateMin)
                <b>Tgl Appraisal:</b> {{ $dateMin }}{{ $dateMax && $dateMax !== $dateMin ? ' s/d '.$dateMax : '' }}<br>
                @endif
                <b>Evaluator dalam range ini:</b> {{ $evalNames ?: '-' }}
            </div>
        </div>
        <div style="text-align:right; flex-shrink:0;">
            <div style="font-size:11px; color:#3b82f6; font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">STATUS SIGNATURE BATCH</div>
            <div style="font-size:28px; font-weight:900; color:{{ $sigSlotTotal > 0 && $sigCount >= $sigSlotTotal ? '#166534' : ($sigCount > 0 ? '#1D4ED8' : '#94A3B8') }};">
                {{ $sigCount }} / {{ $sigSlotTotal }}
            </div>
            <div style="font-size:11px; color:#64748B;">slot ditandatangani</div>
            @if($sigBatch)
            <div style="font-size:10px; color:#94A3B8; margin-top:4px;">Batch #{{ $sigBatch->id }}</div>
            @endif
        </div>
    </div>

    {{-- ── FILTER FORM ── --}}
    <form method="GET" id="filter-form"
          style="background:white; border:1.5px solid #E2E8F0; border-radius:10px; padding:14px 18px; margin-bottom:24px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">

        <div>
            <label style="font-size:11px; color:#64748B; font-weight:600; display:block; margin-bottom:4px;">RANGE TANGGAL APPRAISAL</label>
            <input type="text" id="date_range_picker"
                   placeholder="Pilih range tanggal..."
                   data-from="{{ $dateFrom }}" data-to="{{ $dateTo }}"
                   style="border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 12px; font-size:13px; outline:none; width:240px;"
                   onfocus="this.style.borderColor='#2563EB'" onblur="this.style.borderColor='#E2E8F0'">
            <input type="hidden" name="date_from" id="date_from_inp" value="{{ $dateFrom }}">
            <input type="hidden" name="date_to"   id="date_to_inp"   value="{{ $dateTo }}">
        </div>

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

        <button type="submit"
                style="background:#2563EB; color:white; border:none; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
            Terapkan Filter
        </button>

        @if($dateFrom || $dateTo || $periodId)
        <a href="{{ route('appraisals.report-employee', $employee->id) }}"
           style="padding:8px 14px; background:#F1F5F9; color:#64748B; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;">
            Reset
        </a>
        <div style="font-size:11px; color:#3b82f6; padding:4px 0; align-self:center;">
            &#128197; Range aktif:
            {{ $dateFrom ?? '—' }} {{ ($dateFrom && $dateTo) ? 's/d ' . $dateTo : '' }}
        </div>
        @endif
    </form>

    @if($appraisals->isEmpty())
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; padding:48px; text-align:center; color:#94A3B8;">
        <div style="font-size:40px; margin-bottom:12px;">📋</div>
        <div style="font-size:15px; font-weight:600; margin-bottom:8px;">Tidak ada appraisal dalam range ini</div>
        <div style="font-size:13px;">Coba ubah filter tanggal atau periode.</div>
    </div>
    @else

    {{-- ── INFORMASI KARYAWAN ── --}}
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; padding:0; margin-bottom:24px; overflow:hidden;">
        <div style="background:#F8FAFC; padding:12px 18px; border-bottom:1px solid #E2E8F0; border-left:4px solid #2563EB;">
            <span style="font-size:13px; font-weight:700; color:#1e3a8a;">&#128100; Informasi Karyawan</span>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:0;">
            {{-- Left col --}}
            <div style="padding:16px 18px; border-right:1px solid #F1F5F9;">
                @php
                    $infoLeft = [
                        'Batch Appraisal'  => 'Karyawan #' . $employee->id,
                        'Nama Lengkap'     => strtoupper($employee->full_name),
                        'Jabatan'          => $employee->jabatan ?? '-',
                        'Departemen'       => $employee->department?->name ?? '-',
                        'ID Training'      => $employee->id,
                    ];
                @endphp
                @foreach($infoLeft as $lbl => $val)
                <div style="display:flex; gap:8px; padding:5px 0; border-bottom:1px solid #F8FAFC; font-size:12px;">
                    <span style="width:130px; color:#64748B; flex-shrink:0;">{{ $lbl }}</span>
                    <span style="color:#1e293b; font-weight:600;">{{ $val }}</span>
                </div>
                @endforeach
            </div>
            {{-- Middle col --}}
            <div style="padding:16px 18px; border-right:1px solid #F1F5F9;">
                @php
                    $dateRange = $dateMin
                        ? ($dateMax && $dateMax !== $dateMin ? "{$dateMin} s/d {$dateMax}" : $dateMin)
                        : '-';
                    $infoMid = [
                        'Label Periode'    => $periodName,
                        'Tgl Appraisal'    => $dateRange,
                        'No. Komputer'     => $employee->nokom ?? $employee->employee_number ?? '-',
                        'Tgl. Join'        => $employee->join_date?->format('d M Y') ?? '-',
                        'Jumlah Penilai'   => $appraisals->count() . ' evaluator',
                        'Batch Signature'  => $sigBatch ? '#' . $sigBatch->id : '-',
                        'Status Signature' => $sigCount . ' / ' . $sigSlotTotal . ' slot',
                        'Chunk Matriks'    => $chunkCount . ' bagian',
                    ];
                @endphp
                @foreach($infoMid as $lbl => $val)
                <div style="display:flex; gap:8px; padding:5px 0; border-bottom:1px solid #F8FAFC; font-size:12px;">
                    <span style="width:130px; color:#64748B; flex-shrink:0;">{{ $lbl }}</span>
                    <span style="color:#1e293b; font-weight:600;">{{ $val }}</span>
                </div>
                @endforeach
            </div>
            {{-- Right: Nilai rata-rata besar --}}
            <div style="padding:24px 28px; display:flex; flex-direction:column; align-items:center; justify-content:center; min-width:180px;">
                <div style="font-size:10px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:.07em; margin-bottom:8px;">NILAI RATA-RATA AKHIR</div>
                @php $gradeBand = \App\Support\AppraisalGrading::band($overallGrade); @endphp
                <div style="font-size:52px; font-weight:900; color:{{ $gradeBand['color'] ?? '#64748B' }}; line-height:1;">
                    {{ $overallAvg !== null ? number_format($overallAvg, 2) : '—' }}
                </div>
                <div style="margin-top:10px; padding:4px 16px; border-radius:99px; background:{{ $gradeBand['bg'] ?? '#F1F5F9' }}; color:{{ $gradeBand['color'] ?? '#64748B' }}; font-size:12px; font-weight:800; letter-spacing:.06em;">
                    {{ $overallGrade ?? 'N/A' }}
                </div>
            </div>
        </div>
    </div>

    {{-- ── SECTION 1: MATRIKS PENILAIAN ── --}}
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; margin-bottom:24px; overflow:hidden;">
        <div style="background:#F8FAFC; padding:12px 18px; border-bottom:1px solid #E2E8F0; border-left:4px solid #2563EB;">
            <span style="font-size:13px; font-weight:700; color:#1e3a8a;">&#128202; Section 1: Matriks Penilaian Kuantitatif</span>
        </div>

        @if(count($matrix) === 0)
        <div style="padding:32px; text-align:center; color:#94A3B8; font-size:13px;">Belum ada data indikator penilaian.</div>
        @else
        @foreach($chunks as $ci => $chunk)
        @php $totalChunks = $chunks->count(); @endphp
        <div style="{{ !$loop->first ? 'border-top:2px solid #E2E8F0;' : '' }} padding:0;">
            <div style="padding:10px 18px; background:#EFF6FF; display:flex; align-items:center; gap:10px;">
                <span style="font-size:11px; font-weight:700; color:#1D4ED8; background:#BFDBFE; padding:3px 10px; border-radius:99px;">
                    Bagian {{ $ci + 1 }} / {{ $totalChunks }}
                </span>
                <span style="font-size:11px; color:#3b82f6;">{{ $chunk->count() }} evaluator di bagian ini</span>
            </div>
            <div style="overflow-x:auto; padding:0 0 0 0;">
                <table style="width:100%; border-collapse:collapse; font-size:11.5px; min-width:500px;">
                    <thead>
                        <tr style="background:#1e3a8a; color:white;">
                            <th style="padding:10px 14px; text-align:left; font-weight:600; min-width:200px;">Kriteria Penilaian</th>
                            @foreach($chunk as $a)
                            <th style="padding:10px 10px; text-align:center; font-weight:600; min-width:90px; white-space:nowrap; {{ !$a->included_in_score ? 'opacity:.5;' : '' }}">
                                {{ $a->appraiser?->name ?? 'Evaluator' }}<br>
                                <span style="font-size:9px; opacity:.7;">(Eval {{ $evaluatorNumber[$a->id] }})</span>
                                @if(!$a->included_in_score)
                                    <br><span style="font-size:9px; background:#FEE2E2; color:#991B1B; padding:1px 6px; border-radius:99px;">Dikecualikan</span>
                                @endif
                                @if(in_array((string) auth()->user()->role, ['admin','hrd'], true))
                                <form method="POST" action="{{ route('appraisals.toggle-include-in-score', $a) }}" style="margin-top:4px;">
                                    @csrf
                                    <button type="submit" style="font-size:9px; background:{{ $a->included_in_score ? '#FEF2F2' : '#F0FDF4' }}; color:{{ $a->included_in_score ? '#B91C1C' : '#15803D' }}; border:1px solid {{ $a->included_in_score ? '#FECACA' : '#BBF7D0' }}; border-radius:6px; padding:2px 6px; cursor:pointer;">
                                        {{ $a->included_in_score ? 'Exclude' : 'Include lagi' }}
                                    </button>
                                </form>
                                @endif
                            </th>
                            @endforeach
                            <th style="padding:10px 10px; text-align:center; font-weight:600; min-width:75px; background:#1e40af;">Rata-rata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matrix as $mi => $mRow)
                        @php
                            $rAvg = $mRow['avg'];
                            $rAvgStyle = $rAvg === null ? 'color:#94A3B8;' :
                                ($rAvg >= 3.5 ? 'color:#166534;font-weight:700;' :
                                ($rAvg < 2.5  ? 'color:#991B1B;font-weight:700;' : 'color:#1e293b;font-weight:700;'));
                        @endphp
                        <tr style="{{ $mi % 2 === 1 ? 'background:#F8FAFC;' : '' }}">
                            <td style="padding:8px 14px; color:#374151; border-bottom:1px solid #F1F5F9;">{{ $mRow['label'] }}</td>
                            @foreach($chunk as $a)
                            @php $s = $mRow['scores'][$a->id] ?? null; @endphp
                            <td style="padding:8px 10px; text-align:center; border-bottom:1px solid #F1F5F9; {{ !$a->included_in_score ? 'opacity:.4;' : '' }}
                                       {{ $s !== null && $s >= 4 ? 'color:#166534;' : ($s !== null && $s <= 2 ? 'color:#991B1B;' : 'color:#374151;') }}">
                                {{ $s !== null ? $s : '—' }}
                            </td>
                            @endforeach
                            <td style="padding:8px 10px; text-align:center; border-bottom:1px solid #F1F5F9; background:#EFF6FF; {{ $rAvgStyle }}">
                                {{ $rAvg !== null ? number_format($rAvg, 2) : '—' }}
                            </td>
                        </tr>
                        @endforeach
                        {{-- RATA-RATA PER EVALUATOR ROW --}}
                        <tr style="background:#DBEAFE;">
                            <td style="padding:9px 14px; font-weight:700; color:#1e3a8a; font-size:12px;">RATA-RATA PER EVALUATOR</td>
                            @foreach($chunk as $a)
                            @php $ea = $evalAvgs[$a->id] ?? null; @endphp
                            <td style="padding:9px 10px; text-align:center; font-weight:700; {{ !$a->included_in_score ? 'opacity:.4;' : '' }}
                                       {{ $ea !== null && $ea >= 3.5 ? 'color:#166534;' : ($ea !== null && $ea < 2.5 ? 'color:#991B1B;' : 'color:#1e3a8a;') }}">
                                {{ $ea !== null ? number_format($ea, 2) : '—' }}
                            </td>
                            @endforeach
                            <td style="padding:9px 10px; text-align:center; font-weight:800; color:#1e3a8a; background:#BFDBFE;">
                                {{ $matrixOverallAvg !== null ? number_format($matrixOverallAvg, 2) : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
        @endif
    </div>

    {{-- ── SECTION 1B: KOMENTAR PER KRITERIA ── --}}
    @php
        $criteriaComments = collect($matrix)->flatMap(function ($mRow) use ($appraisals, $evaluatorNumber) {
            return collect($mRow['comments'] ?? [])
                ->filter(fn ($c) => filled($c))
                ->map(function ($comment, $appraisalId) use ($mRow, $appraisals, $evaluatorNumber) {
                    $a = $appraisals->firstWhere('id', (int) $appraisalId);
                    return [
                        'criteria'  => $mRow['label'],
                        'evaluator' => 'Evaluator ' . ($evaluatorNumber[$appraisalId] ?? '?') . ' — ' . ($a?->appraiser?->name ?? 'Evaluator'),
                        'score'     => $mRow['scores'][$appraisalId] ?? null,
                        'comment'   => $comment,
                    ];
                })->values();
        })->values();
    @endphp
    @if($criteriaComments->isNotEmpty())
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; margin-bottom:24px; overflow:hidden;">
        <div style="background:#F8FAFC; padding:12px 18px; border-bottom:1px solid #E2E8F0; border-left:4px solid #7C3AED;">
            <span style="font-size:13px; font-weight:700; color:#5B21B6;">&#128221; Komentar Evaluator per Kriteria</span>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:12px; min-width:600px;">
                <thead>
                    <tr style="background:#1e3a8a; color:white;">
                        <th style="padding:10px 14px; text-align:left; min-width:180px;">Kriteria</th>
                        <th style="padding:10px 12px; text-align:left; min-width:160px;">Evaluator</th>
                        <th style="padding:10px 12px; text-align:center; width:60px;">Skor</th>
                        <th style="padding:10px 12px; text-align:left;">Komentar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($criteriaComments as $row)
                    <tr style="{{ $loop->even ? 'background:#F8FAFC;' : '' }}">
                        <td style="padding:9px 14px; border-bottom:1px solid #F1F5F9; vertical-align:top; color:#1e3a8a; font-weight:600;">{{ $row['criteria'] }}</td>
                        <td style="padding:9px 12px; border-bottom:1px solid #F1F5F9; vertical-align:top; color:#475569;">{{ $row['evaluator'] }}</td>
                        <td style="padding:9px 12px; border-bottom:1px solid #F1F5F9; vertical-align:top; text-align:center; font-weight:700; color:#374151;">{{ $row['score'] ?? '-' }}</td>
                        <td style="padding:9px 12px; border-bottom:1px solid #F1F5F9; vertical-align:top; color:#374151;">{{ $row['comment'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── SECTION 2: NARASI & FEEDBACK ── --}}
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; margin-bottom:24px; overflow:hidden;">
        <div style="background:#F8FAFC; padding:12px 18px; border-bottom:1px solid #E2E8F0; border-left:4px solid #2563EB;">
            <span style="font-size:13px; font-weight:700; color:#1e3a8a;">&#128172; Section 2: Ringkasan Narasi &amp; Feedback</span>
        </div>
        @if(empty($narratives))
        <div style="padding:32px; text-align:center; color:#94A3B8; font-size:13px;">Belum ada narasi.</div>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:12px; min-width:700px;">
                <thead>
                    <tr style="background:#1e3a8a; color:white;">
                        <th style="padding:10px 14px; text-align:left; min-width:160px;">Evaluator &amp; Status</th>
                        <th style="padding:10px 12px; text-align:left; min-width:180px;">Saran</th>
                        <th style="padding:10px 12px; text-align:left; min-width:180px;">Kritik / Area Perbaikan</th>
                        <th style="padding:10px 12px; text-align:left; min-width:160px;">Catatan Lain</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($narratives as $n)
                    <tr style="{{ $loop->even ? 'background:#F8FAFC;' : '' }} {{ !$n['included_in_score'] ? 'opacity:.45;' : '' }}">
                        <td style="padding:10px 14px; border-bottom:1px solid #F1F5F9; vertical-align:top;">
                            <div style="font-weight:700; color:#1e3a8a;">Evaluator {{ $n['no'] }}</div>
                            <div style="font-size:11px; color:#475569; margin-top:2px;">{{ $n['name'] }}</div>
                            <div style="margin-top:4px; display:flex; gap:4px; flex-wrap:wrap;">
                                <span style="font-size:10px; padding:2px 8px; border-radius:99px; background:#EFF6FF; color:#1D4ED8; font-weight:600;">
                                    {{ $n['proposed_status'] ?: '-' }}
                                </span>
                                @if(!$n['included_in_score'])
                                    <span style="font-size:10px; padding:2px 8px; border-radius:99px; background:#FEE2E2; color:#991B1B; font-weight:600;">Dikecualikan</span>
                                @endif
                            </div>
                            <div style="font-size:10px; color:#94A3B8; margin-top:3px;">{{ $n['date'] }}</div>
                        </td>
                        <td style="padding:10px 12px; border-bottom:1px solid #F1F5F9; vertical-align:top; color:#374151;">{{ $n['strengths'] ?: '—' }}</td>
                        <td style="padding:10px 12px; border-bottom:1px solid #F1F5F9; vertical-align:top; color:#374151;">{{ $n['improvements'] ?: '—' }}</td>
                        <td style="padding:10px 12px; border-bottom:1px solid #F1F5F9; vertical-align:top; color:#374151;">{{ $n['notes'] ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── SECTION 2B: KEPUTUSAN KONTRAK ── --}}
    @php
        $canManageContractDecision = in_array(auth()->user()->role, ['admin','hrd']);
        $durationLabels = [
            'tidak_diperpanjang' => 'Tidak Diperpanjang',
            '3_bulan'            => '3 Bulan',
            '6_bulan'            => '6 Bulan',
            '1_tahun'            => '1 Tahun',
            '2_tahun'            => '2 Tahun',
            'custom'             => 'Custom',
        ];
    @endphp
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; margin-bottom:24px; overflow:hidden;">
        <div style="background:#F8FAFC; padding:12px 18px; border-bottom:1px solid #E2E8F0; border-left:4px solid #7C3AED;">
            <span style="font-size:13px; font-weight:700; color:#5B21B6;">&#128203; Masa Kontrak Diperpanjang</span>
        </div>
        <div style="padding:18px;">
            @if(!$latestAppraisal)
                <div style="color:#94A3B8; font-size:13px;">Belum ada appraisal untuk disimpan keputusannya.</div>
            @elseif(!$canManageContractDecision)
                <div style="font-size:13px; color:#374151;">
                    {{ $durationLabels[$latestAppraisal->proposed_contract_duration] ?? 'Belum ditentukan' }}
                    @if($latestAppraisal->contract_extension_effective_date)
                        &mdash; efektif {{ $latestAppraisal->contract_extension_effective_date->format('d-m-Y') }}
                    @endif
                </div>
            @else
            <div x-data="{
                    duration: '{{ $latestAppraisal->proposed_contract_duration ?? '' }}',
                    effectiveDate: '{{ $latestAppraisal->contract_extension_effective_date?->format('Y-m-d') ?? '' }}',
                    setDuration(d) {
                        this.duration = d;
                        const months = { '3_bulan': 3, '6_bulan': 6, '1_tahun': 12, '2_tahun': 24 }[d];
                        if (d === 'tidak_diperpanjang') {
                            this.effectiveDate = '';
                            return;
                        }
                        if (d === 'custom' || !months) {
                            // Custom: HRD isi tanggalnya sendiri, tidak dihitung otomatis.
                            return;
                        }
                        const base = new Date();
                        base.setMonth(base.getMonth() + months);
                        this.effectiveDate = base.toISOString().slice(0, 10);
                    }
                 }">
                <form method="POST" action="{{ route('appraisals.report-employee.contract-decision', $employee->id) }}">
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $periodId }}">
                    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:12px;">
                        @foreach($durationLabels as $val => $label)
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px; color:#374151; cursor:pointer;">
                            <input type="radio" name="proposed_contract_duration" value="{{ $val }}"
                                   x-model="duration" @click="setDuration('{{ $val }}')"
                                   style="width:15px; height:15px; accent-color:#7C3AED;">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <label style="font-size:12px; color:#64748B;" x-text="duration === 'custom' ? 'Tanggal Efektif (isi manual)' : 'Tanggal Efektif (otomatis terisi saat pilih durasi, bisa diubah manual)'"></label>
                        <input type="date" name="contract_extension_effective_date" x-model="effectiveDate"
                               :disabled="duration === 'tidak_diperpanjang'"
                               style="border:1.5px solid #E2E8F0; border-radius:8px; padding:6px 10px; font-size:13px; outline:none;">
                    </div>
                    <button type="submit" style="margin-top:12px; background:#5B21B6; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer;">
                        Simpan Keputusan
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>

    {{-- ── SECTION 3: TANDA TANGAN DIGITAL ── --}}
    @php
        $currentUserId   = auth()->id();
        $canManageSigner = in_array(auth()->user()->role, ['admin','hrd']);
        $slotCount       = $sigBatch?->slots->count() ?? 0;
    @endphp

    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; margin-bottom:24px; overflow:hidden;">
        <div style="background:#F8FAFC; padding:12px 18px; border-bottom:1px solid #E2E8F0; border-left:4px solid #2563EB; display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:13px; font-weight:700; color:#1e3a8a;">&#128396; Section 3: Tanda Tangan Digital Appraisal</span>
            @if($sigBatch)
            <span style="font-size:11px; color:#94A3B8;">Batch #{{ $sigBatch->id }} &mdash; {{ $sigBatch->signedCount() }} / {{ $slotCount }} ditandatangani</span>
            @else
            <span style="font-size:11px; color:#94A3B8;">Belum ada batch signature</span>
            @endif
        </div>

        @if(!$sigBatch)
        <div style="padding:32px; text-align:center; color:#94A3B8; font-size:13px;">
            Tidak ada batch signature tersedia. Tambahkan appraisal terlebih dahulu.
        </div>
        @else
        <div style="padding:18px;">
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px; margin-bottom:14px;">
                @foreach($sigBatch->slots as $slot)
                @include('appraisals.partials.sig_slot', ['slot'=>$slot, 'currentUserId'=>$currentUserId, 'canManageSigner'=>$canManageSigner, 'categoryCandidates'=>$categoryCandidates, 'employeeId'=>$employee->id])
                @endforeach
            </div>

            @if($canManageSigner && $slotCount < \App\Models\AppraisalBatchSignatureSlot::MAX_SLOTS)
            <form method="POST" action="{{ route('appraisals.report-employee.add-signature-slot', $employee->id) }}">
                @csrf
                <input type="hidden" name="batch_id" value="{{ $sigBatch->id }}">
                <button type="submit" style="border:1.5px dashed #CBD5E1; color:#64748B; background:white; padding:8px 16px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer;">
                    + Tambah TTD (maks {{ \App\Models\AppraisalBatchSignatureSlot::MAX_SLOTS }})
                </button>
            </form>
            @endif
        </div>
        @endif
    </div>

    {{-- ── SECTION 4: EXPORT REPORT ── --}}
    <div style="background:white; border-radius:14px; border:1.5px solid #E2E8F0; margin-bottom:24px; overflow:hidden;">
        <div style="background:#F8FAFC; padding:12px 18px; border-bottom:1px solid #E2E8F0; border-left:4px solid #059669;">
            <span style="font-size:13px; font-weight:700; color:#065F46;">&#128196; Section 4: Export Report Karyawan</span>
        </div>
        <div style="padding:18px;">
            <p style="font-size:12px; color:#64748B; margin:0 0 14px;">
                Semua tombol export di bawah akan menghasilkan file untuk <b>{{ $employee->full_name }}</b>
                sesuai range tanggal aktif.
                Centang evaluator yang namanya boleh ditampilkan di dalam file hasil export.
            </p>

            <form method="POST" id="export-form" target="_blank">
                @csrf
                <input type="hidden" name="period_id" value="{{ $periodId }}">
                <input type="hidden" name="date_from"  value="{{ $dateFrom }}">
                <input type="hidden" name="date_to"    value="{{ $dateTo }}">

                {{-- Evaluator checkboxes --}}
                <div style="margin-bottom:16px;">
                    <div style="font-size:11px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">
                        Evaluator yang namanya boleh tampil di export:
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                        @foreach($appraisals as $a)
                        <label style="display:flex; align-items:center; gap:8px; padding:7px 12px; border:1.5px solid #E2E8F0; border-radius:8px; cursor:pointer; font-size:12px; color:#374151;">
                            <input type="checkbox" name="evaluator_ids[]" value="{{ $a->id }}" checked
                                   style="width:15px; height:15px; accent-color:#2563EB;">
                            <span>
                                <b>Evaluator {{ $evaluatorNumber[$a->id] }}</b> &mdash; {{ $a->appraiser?->name ?? 'Evaluator' }}
                                <span style="font-size:10px; color:#94A3B8;">({{ $a->date_appraised?->format('d M Y') ?? '-' }})</span>
                            </span>
                        </label>
                        @endforeach
                    </div>
                    <div style="margin-top:8px; display:flex; gap:8px;">
                        <button type="button" onclick="document.querySelectorAll('#export-form input[type=checkbox]').forEach(c=>c.checked=true)"
                                style="font-size:11px; padding:4px 10px; background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; border-radius:6px; cursor:pointer;">
                            Pilih Semua
                        </button>
                        <button type="button" onclick="document.querySelectorAll('#export-form input[type=checkbox]').forEach(c=>c.checked=false)"
                                style="font-size:11px; padding:4px 10px; background:#F1F5F9; color:#64748B; border:1px solid #E2E8F0; border-radius:6px; cursor:pointer;">
                            Batalkan Semua
                        </button>
                    </div>
                </div>

                {{-- Export Buttons --}}
                <input type="hidden" name="download" id="pdfDownloadFlag" value="1">
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit"
                            formaction="{{ route('appraisals.export-employee-pdf', $employee->id) }}"
                            onclick="document.getElementById('pdfDownloadFlag').value='1'"
                            style="background:#DC2626; color:white; border:none; padding:9px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        &#x2B07; Export PDF
                    </button>
                    <button type="submit"
                            formaction="{{ route('appraisals.export-employee-excel', $employee->id) }}"
                            style="background:#059669; color:white; border:none; padding:9px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        &#x2B07; Export Excel
                    </button>
                    <button type="button" disabled
                            style="background:#94A3B8; color:white; border:none; padding:9px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:not-allowed; display:inline-flex; align-items:center; gap:6px; opacity:.6;"
                            title="Library DOC belum tersedia">
                        &#x2B07; Export DOC
                    </button>
                    <button type="submit"
                            formaction="{{ route('appraisals.export-employee-pdf', $employee->id) }}"
                            formtarget="_blank"
                            onclick="document.getElementById('pdfDownloadFlag').value='0'"
                            title="Buka preview PDF di tab baru, lalu print dari sana"
                            style="background:#475569; color:white; border:none; padding:9px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        &#x1F5A8; Print Out
                    </button>
                </div>
            </form>
        </div>
    </div>

    @endif {{-- end if appraisals not empty --}}

    {{-- ── SIGNATURE MODAL ── --}}
    <div x-show="isOpen"
         style="position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9000; display:flex; align-items:center; justify-content:center;"
         x-cloak @click.self="isOpen=false">
        <div style="background:white; border-radius:16px; padding:24px; width:520px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,.3);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div>
                    <div style="font-size:15px; font-weight:700; color:#1e293b;">Tanda Tangan Digital</div>
                    <div style="font-size:11px; color:#64748B;" x-text="roleLabel"></div>
                </div>
                <button @click="isOpen=false"
                        style="background:#F1F5F9; border:none; width:32px; height:32px; border-radius:6px; cursor:pointer; font-size:18px; color:#64748B;">
                    &times;
                </button>
            </div>
            <canvas id="sig-canvas"
                    style="border:2px solid #E2E8F0; border-radius:10px; width:100%; touch-action:none; cursor:crosshair;"
                    width="460" height="200"></canvas>
            <div style="font-size:11px; color:#94A3B8; margin:6px 0 14px; text-align:center;">
                Gambar tanda tangan Anda di atas. Gunakan mouse atau sentuh layar.
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button @click="clearSig()"
                        style="background:#F1F5F9; color:#64748B; border:1px solid #E2E8F0; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                    Bersihkan
                </button>
                <button @click="saveSig()"
                        style="background:#2563EB; color:white; border:none; padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                    Simpan Tanda Tangan
                </button>
            </div>
        </div>
    </div>

    {{-- Hidden form for signature POST --}}
    <form id="sig-form" method="POST" action="" style="display:none;">
        @csrf
        <input type="hidden" id="sig-slot-id" name="slot_id">
        <input type="hidden" id="sig-data"    name="signature_data">
    </form>

</div>

<script>
// ── Flatpickr date range ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var picker = document.getElementById('date_range_picker');
    if (!picker) return;
    var df = picker.dataset.from;
    var dt = picker.dataset.to;
    var defaults = [];
    if (df) defaults.push(df);
    if (dt && dt !== df) defaults.push(dt);

    flatpickr(picker, {
        mode: 'range',
        dateFormat: 'Y-m-d',
        locale: 'id',
        defaultDate: defaults.length ? defaults : undefined,
        onChange: function (dates) {
            document.getElementById('date_from_inp').value = dates[0] ? flatpickr.formatDate(dates[0], 'Y-m-d') : '';
            document.getElementById('date_to_inp').value   = dates[1] ? flatpickr.formatDate(dates[1], 'Y-m-d') : '';
        }
    });
});

// ── Alpine.js signature modal ─────────────────────────────────────────────
function sigModal() {
    return {
        isOpen: false,
        roleLabel: '',
        sigPad: null,

        openFor(batchId, slotId, label, actionUrl) {
            this.roleLabel = label;
            this.isOpen    = true;
            var form = document.getElementById('sig-form');
            form.action = actionUrl;
            document.getElementById('sig-slot-id').value = slotId;
            this.$nextTick(function () {
                var canvas = document.getElementById('sig-canvas');
                if (this.sigPad) this.sigPad.off();
                this.sigPad = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)', penColor: 'rgb(10,50,120)' });
            }.bind(this));
        },

        clearSig() {
            this.sigPad && this.sigPad.clear();
        },

        saveSig() {
            if (!this.sigPad || this.sigPad.isEmpty()) {
                alert('Silakan buat tanda tangan terlebih dahulu.');
                return;
            }
            document.getElementById('sig-data').value = this.sigPad.toDataURL('image/png');
            document.getElementById('sig-form').submit();
        }
    };
}
</script>
@endsection
