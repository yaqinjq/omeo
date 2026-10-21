@extends('layouts.app')
@section('title', 'Setting Backdate Attendance')
@section('content')
<div class="p-4" style="max-width:700px;">
    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Setting Backdate Attendance (MKO)</h1>
    <p style="font-size:12px; color:#94A3B8; margin:0 0 16px 0;">Backdate NONAKTIF total (termasuk untuk Admin Finance) selama batas hari kosong. Lihat README bagian Backdate.</p>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✗ {{ session('error') }}</div>
    @endif

    <div style="background:{{ $setting->isEnabled() ? '#F0FDF4' : '#FFFBEB' }}; border:1.5px solid {{ $setting->isEnabled() ? '#BBF7D0' : '#FDE68A' }}; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px; font-weight:600; color:{{ $setting->isEnabled() ? '#166534' : '#92400E' }};">
        Status: {{ $setting->isEnabled() ? 'AKTIF — maksimum ' . $setting->max_backdate_days . ' hari mundur' : 'NONAKTIF (batas hari belum diisi)' }}
    </div>

    <form method="POST" action="{{ route('sport.admin.backdate.settings.update') }}" style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:14px; margin-bottom:20px; display:flex; gap:10px; align-items:end;">
        @csrf
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569; display:block;">Maksimum Hari Mundur</label>
            <input type="number" min="1" max="365" name="max_backdate_days" value="{{ $setting->max_backdate_days }}" placeholder="Kosongkan = nonaktif" style="border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px; width:200px;">
        </div>
        <button type="submit" style="background:#4F46E5; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Simpan</button>
    </form>

    <h2 style="font-size:14px; font-weight:800; color:#1e293b; margin:0 0 8px 0;">Audit Trail Terakhir</h2>
    <table style="width:100%; border-collapse:collapse; font-size:12px; background:white; border:1.5px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <thead><tr style="background:#FAFAFA;"><th style="padding:7px;">Waktu</th><th style="padding:7px;">Karyawan</th><th style="padding:7px;">Aksi</th><th style="padding:7px;">Sebelum</th><th style="padding:7px;">Sesudah</th><th style="padding:7px;">Oleh</th><th style="padding:7px;">Alasan</th></tr></thead>
        <tbody>
            @forelse($audits as $a)
            <tr style="border-top:1px solid #F1F5F9; text-align:center;">
                <td style="padding:6px;">{{ $a->performed_at->format('d/m H:i') }}</td>
                <td style="padding:6px;">{{ $a->employee->full_name ?? '—' }}</td>
                <td style="padding:6px;">{{ $a->action }}</td>
                <td style="padding:6px;">{{ $a->before_confirmed_at?->format('d/m H:i') ?? '—' }}</td>
                <td style="padding:6px;">{{ $a->after_confirmed_at->format('d/m H:i') }}</td>
                <td style="padding:6px;">{{ $a->actor->name ?? '—' }}</td>
                <td style="padding:6px; text-align:left;">{{ $a->reason }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:16px; text-align:center; color:#94A3B8;">Belum ada riwayat backdate.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
