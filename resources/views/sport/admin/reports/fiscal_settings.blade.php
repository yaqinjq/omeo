@extends('layouts.app')
@section('title', 'Setting Fiscal Year — Laporan Sport')
@section('content')
<div class="p-4" style="max-width:600px;">
    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Setting Fiscal Year (Laporan Triwulanan Owner)</h1>
    <p style="font-size:12px; color:#94A3B8; margin:0 0 16px 0;">Selama belum disetel, laporan triwulanan memakai periode kustom — bukan quarter fiskal, bukan Q1-Q4.</p>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✓ {{ session('success') }}</div>
    @endif

    <div style="background:{{ $setting->isFiscalConfigured() ? '#F0FDF4' : '#FFFBEB' }}; border:1.5px solid {{ $setting->isFiscalConfigured() ? '#BBF7D0' : '#FDE68A' }}; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px; font-weight:600; color:{{ $setting->isFiscalConfigured() ? '#166534' : '#92400E' }};">
        Status: {{ $setting->isFiscalConfigured() ? 'Fiscal year dimulai bulan ke-' . $setting->fiscal_year_start_month : 'BELUM DISETEL — laporan triwulanan memakai periode kustom sementara' }}
    </div>

    <form method="POST" action="{{ route('sport.admin.reports.fiscal-settings.update') }}" style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:14px; display:flex; gap:10px; align-items:end;">
        @csrf
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block;">Bulan Mulai Tahun Fiskal (1-12)</label>
            <input type="number" min="1" max="12" name="fiscal_year_start_month" value="{{ $setting->fiscal_year_start_month }}" placeholder="Kosongkan = belum disetel" style="border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px; width:220px;">
        </div>
        <button type="submit" style="background:#4F46E5; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Simpan</button>
    </form>
    <p style="font-size:10.5px; color:#94A3B8; margin-top:8px;">Contoh: isi "4" kalau tahun fiskal dimulai April. Nilai ini TIDAK diisi otomatis oleh sistem — harus ditentukan Admin Finance/owner.</p>
</div>
@endsection
