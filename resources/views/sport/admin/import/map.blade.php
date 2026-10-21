@extends('layouts.app')
@section('title', 'Pemetaan Kolom Import')
@section('content')
<div class="p-4" style="max-width:600px;">
    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Pemetaan Kolom</h1>
    <p style="font-size:12px; color:#94A3B8; margin:0 0 16px 0;">File: {{ $batch->original_filename }} ({{ $batch->total_rows }} baris data). Kolom identitas HANYA dicocokkan via employee_number/NIK/external_id — tidak lewat nama saja.</p>

    <form method="POST" action="{{ route('sport.admin.import.map.save', $batch) }}" style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:16px; display:grid; gap:12px;">
        @csrf
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block;">Kolom Identifier (employee_number/NIK/external_id, atau ID eksternal Non-MKO) — wajib</label>
            <select name="identifier_column" required style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px;">
                <option value="">— pilih kolom —</option>
                @foreach($headers as $h)<option value="{{ $h }}">{{ $h }}</option>@endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block;">Kolom Nama (opsional, tampilan &amp; wajib untuk Non-MKO)</label>
            <select name="name_column" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px;">
                <option value="">— tidak dipakai —</option>
                @foreach($headers as $h)<option value="{{ $h }}">{{ $h }}</option>@endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block;">Kolom Tipe Peserta (opsional — isi "Non-MKO" untuk tandai peserta luar secara eksplisit)</label>
            <select name="participant_type_column" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px;">
                <option value="">— tidak dipakai (semua dianggap MKO) —</option>
                @foreach($headers as $h)<option value="{{ $h }}">{{ $h }}</option>@endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block;">Kolom Tanggal/Jam Hadir (opsional — default waktu selesai event)</label>
            <select name="attended_at_column" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px;">
                <option value="">— pakai default —</option>
                @foreach($headers as $h)<option value="{{ $h }}">{{ $h }}</option>@endforeach
            </select>
        </div>
        <button type="submit" style="background:#4F46E5; color:white; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Lanjut ke Preview</button>
    </form>
</div>
@endsection
