@extends('layouts.app')
@section('title', 'OMEO Sport')
@section('content')
<div class="p-4">
    <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">🏸 OMEO Sport</h1>
    <p style="color:#64748B; font-size:13px; margin:0 0 20px 0;">Daftar event olahraga internal karyawan OMEO. Kehadiran olahraga bukan presensi kerja.</p>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✗ {{ session('error') }}</div>
    @endif

    <div style="display:flex; flex-direction:column; gap:10px;">
        @forelse($events as $event)
        @php($myReg = $event->registrations->first())
        <a href="{{ route('sport.show', $event) }}" style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:14px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; text-decoration:none;">
            <div>
                <div style="font-weight:700; color:#1e293b; font-size:14px;">{{ $event->title ?: ucfirst($event->sport) }} <span style="color:#7C3AED; font-weight:600; font-size:11.5px;">· {{ ucfirst($event->sport) }}</span></div>
                <div style="font-size:12px; color:#64748B; margin-top:2px;">📍 {{ $event->location }} · 📅 {{ $event->event_date->format('d M Y') }}, {{ $event->start_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('H:i') }}–{{ $event->end_at->copy()->setTimezone(\App\Models\Sport\Event::BUSINESS_TIMEZONE)->format('H:i') }} WIB</div>
                @if($event->has_coach)
                <div style="font-size:11px; color:#B45309; margin-top:2px;">🧑‍🏫 Coach: {{ $event->coach_name ?: '-' }} · Kuota: {{ $event->registrations_count }}/{{ $event->capacity }}</div>
                @endif
            </div>
            <div style="text-align:right;">
                @if($myReg)
                <span style="font-size:10.5px; font-weight:700; padding:3px 10px; border-radius:99px; background:#EFF6FF; color:#1D4ED8;">
                    {{ $myReg->status === 'registered' ? 'Terdaftar' : ($myReg->status === 'cancellation_requested' ? 'Menunggu Pembatalan' : 'Dibatalkan') }}
                </span>
                @else
                <span style="font-size:10.5px; font-weight:700; padding:3px 10px; border-radius:99px; background:#F1F5F9; color:#64748B;">Belum Daftar</span>
                @endif
            </div>
        </a>
        @empty
        <div style="text-align:center; color:#94A3B8; padding:30px; background:#FAFAFA; border-radius:12px; font-size:13px;">Belum ada event yang dipublish.</div>
        @endforelse
    </div>
</div>
@endsection
