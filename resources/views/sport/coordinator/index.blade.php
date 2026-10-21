@extends('layouts.app')
@section('title', 'Kelola Event Sport')
@section('content')
<div class="p-4">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <div>
            <h1 style="font-size:22px; font-weight:800; color:#1e293b; margin:0;">🏸 Kelola Event Sport</h1>
            <p style="color:#64748B; font-size:13px; margin:4px 0 0 0;">Event yang menjadi tanggung jawab Anda sebagai Koordinator</p>
        </div>
        <a href="{{ route('sport.coordinator.create') }}" style="background:#7C3AED; color:white; border:none; padding:9px 18px; border-radius:10px; font-size:13px; font-weight:600; text-decoration:none;">+ Buat Event</a>
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">✗ {{ session('error') }}</div>
    @endif

    <div style="display:flex; flex-direction:column; gap:10px;">
        @forelse($events as $event)
        <div style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:14px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <div style="font-weight:700; color:#1e293b; font-size:14px;">{{ $event->title ?: ucfirst($event->sport) }}
                    <span style="font-size:10px; font-weight:700; padding:2px 8px; border-radius:99px; margin-left:6px;
                        {{ $event->status === 'draft' ? 'background:#F1F5F9;color:#64748B;' : ($event->status === 'cancelled' ? 'background:#FEE2E2;color:#991B1B;' : 'background:#DCFCE7;color:#166534;') }}">
                        {{ ucfirst(str_replace('_',' ', $event->status)) }}
                    </span>
                </div>
                <div style="font-size:12px; color:#64748B; margin-top:2px;">📍 {{ $event->location }} · 📅 {{ $event->event_date->format('d M Y') }} · {{ $event->registrations_count }} peserta terdaftar</div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="{{ route('sport.coordinator.show', $event) }}" style="font-size:12px; font-weight:600; color:#1D4ED8; text-decoration:none;">Roster</a>
                @if($event->status === 'draft')
                <a href="{{ route('sport.coordinator.edit', $event) }}" style="font-size:12px; font-weight:600; color:#B45309; text-decoration:none;">Edit</a>
                <form method="POST" action="{{ route('sport.coordinator.publish', $event) }}" onsubmit="return confirm('Publish event ini? Event akan terlihat & bisa didaftar karyawan.')">
                    @csrf
                    <button type="submit" style="background:none; border:none; font-size:12px; font-weight:600; color:#166534; cursor:pointer; padding:0;">Publish</button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div style="text-align:center; color:#94A3B8; padding:30px; background:#FAFAFA; border-radius:12px; font-size:13px;">Belum ada event. Klik "+ Buat Event" untuk mulai.</div>
        @endforelse
    </div>
</div>
@endsection
