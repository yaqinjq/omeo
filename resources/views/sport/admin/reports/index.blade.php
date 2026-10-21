@extends('layouts.app')
@section('title', 'Laporan OMEO Sport')
@section('content')
<div class="p-4" style="max-width:1000px;">
    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Laporan OMEO Sport (Admin Finance)</h1>
    <p style="font-size:12px; color:#94A3B8; margin:0 0 16px 0;">Peserta unik dan kehadiran (orang-sesi) ditampilkan terpisah — satu orang bisa hadir di beberapa event. Total event selalu dihitung dari periode+sport saja, tidak berubah karena filter grup.</p>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✓ {{ session('success') }}</div>
    @endif

    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:12px;">
        <div>
            <label style="font-size:11px; color:#64748B; display:block;">Dari</label>
            <input type="date" name="from" value="{{ $filters['from'] }}" style="border:1px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:12.5px;">
        </div>
        <div>
            <label style="font-size:11px; color:#64748B; display:block;">Sampai</label>
            <input type="date" name="to" value="{{ $filters['to'] }}" style="border:1px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:12.5px;">
        </div>
        <div>
            <label style="font-size:11px; color:#64748B; display:block;">Cabang Olahraga</label>
            <select name="sport" style="border:1px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:12.5px;">
                <option value="">Semua</option>
                @foreach($sports as $s)
                <option value="{{ $s }}" @selected($filters['sport'] === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:11px; color:#64748B; display:block;">Grup Peserta</label>
            <select name="group" style="border:1px solid #E2E8F0; border-radius:6px; padding:5px 8px; font-size:12.5px;">
                <option value="all" @selected($filters['group'] === 'all')>Semua (MKO + Non-MKO terpisah)</option>
                <option value="mko" @selected($filters['group'] === 'mko')>MKO saja</option>
                <option value="non_mko" @selected($filters['group'] === 'non_mko')>Non-MKO saja</option>
            </select>
        </div>
        <div style="align-self:flex-end;">
            <button type="submit" style="background:#4F46E5; color:white; border:none; padding:7px 16px; border-radius:6px; font-size:12.5px; font-weight:600; cursor:pointer;">Terapkan Filter</button>
        </div>
        <div style="align-self:flex-end;">
            <a href="{{ route('sport.admin.reports.export', request()->query()) }}" style="background:#059669; color:white; padding:7px 16px; border-radius:6px; font-size:12.5px; font-weight:600; text-decoration:none;">⬇ Export Excel</a>
        </div>
        <div style="align-self:flex-end;">
            <a href="{{ route('sport.admin.reports.fiscal-settings') }}" style="background:#F1F5F9; color:#475569; padding:7px 16px; border-radius:6px; font-size:12.5px; font-weight:600; text-decoration:none;">⚙ Setting Fiscal Year</a>
        </div>
    </form>

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:20px;">
        @foreach([
            'Total Event' => $summary['total_events'],
            'Peserta Unik MKO' => $summary['unique_participants_mko'],
            'Peserta Unik Non-MKO' => $summary['unique_participants_non_mko'],
            'Sesi Hadir MKO (orang-sesi)' => $summary['attendance_sessions_mko'],
            'Sesi Hadir Non-MKO (orang-sesi)' => $summary['attendance_sessions_non_mko'],
            'Pembatalan Disetujui' => $summary['cancellations_approved'],
            'Belum Terkonfirmasi' => $summary['not_confirmed'],
        ] as $label => $value)
        <div style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:12px;">
            <div style="font-size:10.5px; color:#94A3B8; font-weight:600;">{{ $label }}</div>
            <div style="font-size:22px; font-weight:800; color:#1e293b;">{{ $value ?? '—' }}</div>
        </div>
        @endforeach
    </div>

    <h2 style="font-size:14px; font-weight:800; color:#1e293b; margin:20px 0 8px 0;">Laporan Bulanan</h2>
    <table style="width:100%; border-collapse:collapse; font-size:12px; background:white; border:1.5px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <thead><tr style="background:#FAFAFA;"><th style="padding:8px;">Bulan</th><th style="padding:8px;">Event</th><th style="padding:8px;">Unik MKO</th><th style="padding:8px;">Unik Non-MKO</th><th style="padding:8px;">Sesi MKO</th><th style="padding:8px;">Sesi Non-MKO</th></tr></thead>
        <tbody>
            @forelse($monthly as $m)
            <tr style="border-top:1px solid #F1F5F9; text-align:center;">
                <td style="padding:7px;">{{ $m['month'] }}</td><td>{{ $m['events'] }}</td><td>{{ $m['unique_mko'] ?? '—' }}</td><td>{{ $m['unique_non_mko'] ?? '—' }}</td><td>{{ $m['sessions_mko'] ?? '—' }}</td><td>{{ $m['sessions_non_mko'] ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:16px; text-align:center; color:#94A3B8;">Tidak ada data pada rentang ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($quarterly['type'] === 'unconfigured')
    <h2 style="font-size:14px; font-weight:800; color:#1e293b; margin:20px 0 8px 0;">Periode Kustom <span style="font-size:10.5px; color:#92400E; font-weight:700;">(BUKAN quarter fiskal — fiscal year belum disetel)</span></h2>
    <p style="font-size:11.5px; color:#92400E; background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:8px 12px; margin:0 0 8px 0;">{{ $quarterly['note'] }} <a href="{{ route('sport.admin.reports.fiscal-settings') }}" style="color:#7C3AED; font-weight:700;">Setel sekarang →</a></p>
    @else
    <h2 style="font-size:14px; font-weight:800; color:#1e293b; margin:20px 0 8px 0;">Laporan Triwulanan (Fiscal Quarter)</h2>
    <p style="font-size:11px; color:#64748B; margin:0 0 8px 0;">{{ $quarterly['note'] }}</p>
    @endif
    <table style="width:100%; border-collapse:collapse; font-size:12px; background:white; border:1.5px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <thead><tr style="background:#FAFAFA;"><th style="padding:8px;">{{ $quarterly['type'] === 'fiscal' ? 'Fiscal Quarter' : 'Periode' }}</th><th style="padding:8px;">Event</th><th style="padding:8px;">Unik MKO</th><th style="padding:8px;">Unik Non-MKO</th><th style="padding:8px;">Sesi MKO</th><th style="padding:8px;">Sesi Non-MKO</th></tr></thead>
        <tbody>
            @forelse($quarterly['rows'] as $q)
            <tr style="border-top:1px solid #F1F5F9; text-align:center;">
                <td style="padding:7px;">{{ $q['label'] }}</td><td>{{ $q['events'] }}</td><td>{{ $q['unique_mko'] ?? '—' }}</td><td>{{ $q['unique_non_mko'] ?? '—' }}</td><td>{{ $q['sessions_mko'] ?? '—' }}</td><td>{{ $q['sessions_non_mko'] ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:16px; text-align:center; color:#94A3B8;">Tidak ada data pada rentang ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="font-size:14px; font-weight:800; color:#1e293b; margin:20px 0 8px 0;">Per Event</h2>
    <table style="width:100%; border-collapse:collapse; font-size:12px; background:white; border:1.5px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <thead><tr style="background:#FAFAFA;"><th style="padding:8px;">Tanggal</th><th style="padding:8px;">Sport</th><th style="padding:8px;">Judul</th><th style="padding:8px;">Registrasi</th><th style="padding:8px;">Hadir MKO</th><th style="padding:8px;">Hadir Non-MKO</th><th style="padding:8px;">Status</th></tr></thead>
        <tbody>
            @forelse($perEvent as $e)
            <tr style="border-top:1px solid #F1F5F9; text-align:center;">
                <td style="padding:7px;">{{ $e['event_date'] }}</td><td>{{ ucfirst($e['sport']) }}</td><td style="text-align:left;">{{ $e['title'] }}</td><td>{{ $e['registrations'] ?? '—' }}</td><td>{{ $e['attendances_mko'] ?? '—' }}</td><td>{{ $e['attendances_non_mko'] ?? '—' }}</td><td>{{ $e['status'] }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:16px; text-align:center; color:#94A3B8;">Tidak ada event pada rentang ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
