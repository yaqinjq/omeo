@extends('layouts.app')
@section('title', 'Peserta Non-MKO — Event')
@section('content')
<div class="p-4" style="max-width:700px;">
    <h1 style="font-size:17px; font-weight:800; color:#1e293b; margin:0 0 4px 0;">Peserta Non-MKO — {{ $event->title ?: ucfirst($event->sport) }}</h1>
    <p style="font-size:12px; color:#94A3B8; margin:0 0 16px 0;">📅 {{ $event->event_date->format('d M Y') }}. Input manual oleh Admin Finance — Non-MKO tidak punya alur daftar/konfirmasi sendiri.</p>

    @if(session('success'))
    <div style="background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:12px;">✓ {{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('sport.admin.events.non-mko-attendances.store', $event) }}" style="background:white; border:1.5px solid #E2E8F0; border-radius:12px; padding:14px; margin-bottom:16px; display:grid; gap:10px;">
        @csrf
        <div>
            <label style="font-size:12px; font-weight:600; color:#475569;">Peserta yang sudah ada</label>
            <select name="participant_id" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px;">
                <option value="">— Atau isi data baru di bawah —</option>
                @foreach($participants as $p)
                <option value="{{ $p->id }}">{{ $p->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <div>
                <label style="font-size:12px; font-weight:600; color:#475569;">Nama (jika peserta baru)</label>
                <input type="text" name="full_name" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:12px; font-weight:600; color:#475569;">Telepon (opsional)</label>
                <input type="text" name="phone" style="width:100%; border:1.5px solid #E2E8F0; border-radius:8px; padding:7px 10px; font-size:13px; box-sizing:border-box;">
            </div>
        </div>
        <button type="submit" style="background:#4F46E5; color:white; border:none; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Catat Partisipasi</button>
    </form>

    <table style="width:100%; border-collapse:collapse; font-size:12.5px; background:white; border:1.5px solid #E2E8F0; border-radius:10px; overflow:hidden;">
        <thead><tr style="background:#FAFAFA;"><th style="padding:8px; text-align:left;">Nama</th><th style="padding:8px;">Dicatat</th></tr></thead>
        <tbody>
            @forelse($event->nonMkoAttendances as $a)
            <tr style="border-top:1px solid #F1F5F9;"><td style="padding:7px;">{{ $a->participant->full_name }}</td><td style="text-align:center;">{{ $a->attended_at->format('d M Y H:i') }}</td></tr>
            @empty
            <tr><td colspan="2" style="padding:16px; text-align:center; color:#94A3B8;">Belum ada peserta Non-MKO untuk event ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
