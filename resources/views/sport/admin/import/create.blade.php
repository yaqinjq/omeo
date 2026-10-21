@extends('layouts.app')
@section('title', 'Import Historis Sport')
@section('content')
<div class="p-4" style="max-width:600px;">
    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Import Historis (Excel/CSV)</h1>
    <p style="font-size:12px; color:#94A3B8; margin:0 0 16px 0;">Tidak ada koneksi API otomatis ke Zoho. File diproses lewat preview &amp; konfirmasi manual sebelum data masuk.</p>

    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✗ {{ session('error') }}</div>
    @endif
    @foreach($errors->all() ?? [] as $e)
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✗ {{ $e }}</div>
    @endforeach

    <form method="POST" action="{{ route('sport.admin.import.store') }}" enctype="multipart/form-data" style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:16px; display:grid; gap:12px;">
        @csrf
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">Event Tujuan</label>
            <select name="sport_event_id" required style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px;">
                <option value="">— Pilih event —</option>
                @foreach($events as $e)
                <option value="{{ $e->id }}">{{ $e->event_date->format('d M Y') }} — {{ $e->title ?: ucfirst($e->sport) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:4px;">File (.csv atau .xlsx)</label>
            <input type="file" name="file" accept=".csv,.txt,.xlsx" required>
        </div>
        <button type="submit" style="background:#4F46E5; color:white; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Upload</button>
    </form>
</div>
@endsection
