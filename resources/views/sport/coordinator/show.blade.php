@extends('layouts.app')
@section('title', 'Roster Event Sport')
@section('content')
<div class="p-4" style="max-width:700px;">
    <a href="{{ route('sport.coordinator.index') }}" style="font-size:12.5px; color:#7C3AED; text-decoration:none; font-weight:600;">← Kembali ke daftar event</a>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin:12px 0;">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin:12px 0;">✗ {{ session('error') }}</div>
    @endif

    <h1 style="font-size:19px; font-weight:800; color:#1e293b; margin:14px 0 4px 0;">{{ $event->title ?: ucfirst($event->sport) }}</h1>
    <p style="font-size:12.5px; color:#64748B; margin:0 0 16px 0;">📍 {{ $event->location }} · 📅 {{ $event->event_date->format('d M Y') }}, {{ $event->start_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('H:i') }}–{{ $event->end_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('H:i') }} WIB</p>

    <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
        <a href="https://wa.me/?text={{ urlencode('Yuk ikut event ' . ($event->title ?: $event->sport) . ': ' . route('sport.show', $event)) }}" target="_blank" rel="noopener"
           style="background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none;">
            📤 Bagikan via WhatsApp (manual)
        </a>
        @if(in_array((string) auth()->user()->role, ['admin', 'finance'], true))
        <a href="{{ route('sport.admin.events.non-mko-attendances.index', $event) }}" style="background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none;">👥 Kelola Peserta Non-MKO</a>
        @endif
    </div>
    <p style="font-size:10.5px; color:#94A3B8; margin:-10px 0 16px 0;">Link WhatsApp hanya berisi URL detail event yang tetap memerlukan login OMEO — bukan auto-login/bearer token.</p>

    @if($pendingCancellations->isNotEmpty())
    <div style="background:#FFFBEB; border:1.5px solid #FDE68A; border-radius:12px; padding:14px; margin-bottom:16px;">
        <h3 style="font-size:13px; font-weight:800; color:#92400E; margin:0 0 10px 0;">⏳ Permintaan Pembatalan Menunggu Keputusan</h3>
        @foreach($pendingCancellations as $cr)
        <div style="display:flex; justify-content:space-between; align-items:center; background:white; border-radius:8px; padding:8px 12px; margin-bottom:6px;">
            <div>
                <div style="font-size:12.5px; font-weight:600; color:#1e293b;">{{ $cr->registration->employee->full_name ?? '—' }}</div>
                @if($cr->reason)<div style="font-size:11px; color:#94A3B8;">Alasan: {{ $cr->reason }}</div>@endif
            </div>
            <div style="display:flex; gap:6px;">
                <form method="POST" action="{{ route('sport.coordinator.cancellations.decide', $cr) }}">
                    @csrf
                    <button type="submit" style="background:#166534; color:white; border:none; padding:5px 12px; border-radius:6px; font-size:11.5px; font-weight:600; cursor:pointer;">Setujui</button>
                </form>
                <form method="POST" action="{{ route('sport.coordinator.cancellations.reject', $cr) }}">
                    @csrf
                    <button type="submit" style="background:#FEE2E2; color:#991B1B; border:none; padding:5px 12px; border-radius:6px; font-size:11.5px; font-weight:600; cursor:pointer;">Tolak</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div style="background:white; border:1.5px solid #E2E8F0; border-radius:14px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
            <thead>
                <tr style="background:#FAFAFA; text-align:left;">
                    <th style="padding:10px 14px; font-weight:700; color:#64748B;">Nama</th>
                    <th style="padding:10px 14px; font-weight:700; color:#64748B;">Status Daftar</th>
                    <th style="padding:10px 14px; font-weight:700; color:#64748B;">Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                @forelse($event->registrations as $reg)
                <tr style="border-top:1px solid #F1F5F9;">
                    <td style="padding:10px 14px; font-weight:600; color:#1e293b;">{{ $reg->employee->full_name ?? '—' }}</td>
                    <td style="padding:10px 14px;">
                        <span style="padding:2px 8px; border-radius:99px; font-size:10.5px; font-weight:700;
                            {{ $reg->status === 'registered' ? 'background:#EFF6FF;color:#1D4ED8;' : ($reg->status === 'cancelled' ? 'background:#FEE2E2;color:#991B1B;' : 'background:#FFFBEB;color:#B45309;') }}">
                            {{ ['registered' => 'Terdaftar', 'cancellation_requested' => 'Ajukan Batal', 'cancelled' => 'Dibatalkan'][$reg->status] ?? $reg->status }}
                        </span>
                    </td>
                    <td style="padding:10px 14px; color:#64748B;">
                        @if($reg->attendance)
                            ✓ Hadir ({{ $reg->attendance->confirmed_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('d M H:i') }} WIB){{ $reg->attendance->is_backdated ? ' · backdated' : '' }}
                        @else
                            — Belum konfirmasi
                        @endif
                        @if(in_array((string) auth()->user()->role, ['admin', 'finance'], true))
                        <details style="margin-top:4px;">
                            <summary style="font-size:10.5px; color:#7C3AED; cursor:pointer;">Backdate</summary>
                            <form method="POST" action="{{ route('sport.admin.backdate.store', $reg) }}" style="margin-top:6px; display:grid; gap:4px;">
                                @csrf
                                <input type="datetime-local" name="confirmed_at" required style="font-size:11px; border:1px solid #E2E8F0; border-radius:5px; padding:3px 5px;">
                                <input type="text" name="reason" placeholder="Alasan (wajib)" required style="font-size:11px; border:1px solid #E2E8F0; border-radius:5px; padding:3px 5px;">
                                <button type="submit" style="font-size:10.5px; background:#7C3AED; color:white; border:none; border-radius:5px; padding:3px 8px; cursor:pointer;">Simpan Backdate</button>
                            </form>
                        </details>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" style="padding:20px; text-align:center; color:#94A3B8;">Belum ada peserta terdaftar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
