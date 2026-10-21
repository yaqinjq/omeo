@extends('layouts.app')
@section('title', 'Preview Import')
@section('content')
<div class="p-4" style="max-width:900px;">
    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Preview Import — {{ $batch->original_filename }}</h1>
    <p style="font-size:12px; color:#94A3B8; margin:0 0 16px 0;">Event tujuan: {{ $batch->event->title ?: ucfirst($batch->event->sport) }} ({{ $batch->event->event_date->format('d M Y') }}). Status batch: <strong>{{ $batch->status }}</strong></p>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✗ {{ session('error') }}</div>
    @endif

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:16px;">
        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:10px; padding:10px; text-align:center;"><div style="font-size:11px; color:#166534;">Valid</div><div style="font-size:20px; font-weight:800; color:#166534;">{{ $batch->valid_rows }}</div></div>
        <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:10px; text-align:center;"><div style="font-size:11px; color:#92400E;">Duplikat</div><div style="font-size:20px; font-weight:800; color:#92400E;">{{ $batch->duplicate_rows }}</div></div>
        <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:10px; padding:10px; text-align:center;"><div style="font-size:11px; color:#991B1B;">Invalid</div><div style="font-size:20px; font-weight:800; color:#991B1B;">{{ $batch->invalid_rows }}</div></div>
        <div style="background:#F1F5F9; border:1px solid #E2E8F0; border-radius:10px; padding:10px; text-align:center;"><div style="font-size:11px; color:#475569;">Tidak Cocok</div><div style="font-size:20px; font-weight:800; color:#475569;">{{ $batch->unmatched_rows }}</div></div>
    </div>

    @if($batch->status !== 'committed')
    <form method="POST" action="{{ route('sport.admin.import.commit', $batch) }}" style="margin-bottom:16px;">
        @csrf
        <button type="submit" style="background:#059669; color:white; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;" @disabled($batch->valid_rows === 0)>
            Konfirmasi &amp; Commit ({{ $batch->valid_rows }} baris valid)
        </button>
    </form>
    @else
    <div style="background:#EFF6FF; color:#1D4ED8; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px; font-weight:600;">✓ Batch ini sudah dikonfirmasi pada {{ $batch->committed_at->format('d M Y H:i') }}.</div>
    @endif

    <table style="width:100%; border-collapse:collapse; font-size:11.5px; background:white; border:1.5px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <thead><tr style="background:#FAFAFA;"><th style="padding:6px;">Baris</th><th style="padding:6px;">Tipe</th><th style="padding:6px;">Status</th><th style="padding:6px; text-align:left;">Keterangan</th></tr></thead>
        <tbody>
            @foreach($rows as $r)
            <tr style="border-top:1px solid #F1F5F9; text-align:center;">
                <td style="padding:5px;">{{ $r->row_number }}</td>
                <td style="padding:5px;">{{ $r->participant_type ?? '—' }}</td>
                <td style="padding:5px; font-weight:700;
                    {{ $r->row_status === 'valid' ? 'color:#166534;' : ($r->row_status === 'duplicate' ? 'color:#92400E;' : ($r->row_status === 'invalid' ? 'color:#991B1B;' : 'color:#475569;')) }}">
                    {{ $r->row_status }}
                </td>
                <td style="padding:5px; text-align:left;">{{ $r->error_message ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
