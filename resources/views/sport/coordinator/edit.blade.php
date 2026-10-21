@extends('layouts.app')
@section('title', 'Edit Event Sport')
@section('content')
<div class="p-4" style="max-width:560px;">
    <h1 style="font-size:20px; font-weight:800; color:#1e293b; margin:0 0 16px 0;">Edit Event Sport</h1>

    @if ($errors->any())
    <div style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:10px; font-size:13px; margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('sport.coordinator.update', $event) }}" style="background:white; border:1.5px solid #E2E8F0; border-radius:14px; padding:20px;">
        @csrf
        @method('PUT')
        @include('sport.coordinator._form')
        <div style="display:flex; gap:8px; margin-top:16px;">
            <button type="submit" style="background:#7C3AED; color:white; border:none; padding:9px 20px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer;">Simpan</button>
            <a href="{{ route('sport.coordinator.index') }}" style="background:#F1F5F9; color:#475569; border:none; padding:9px 20px; border-radius:10px; font-size:13px; font-weight:600; text-decoration:none;">Batal</a>
        </div>
    </form>

    @if($event->status === 'draft')
    <form method="POST" action="{{ route('sport.coordinator.publish', $event) }}" style="margin-top:12px;" onsubmit="return confirm('Publish event ini?')">
        @csrf
        <button type="submit" style="background:#166534; color:white; border:none; padding:9px 20px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer;">Publish Event</button>
    </form>
    @endif

    @if($event->status !== 'cancelled')
    <form method="POST" action="{{ route('sport.coordinator.cancel', $event) }}" style="margin-top:12px;" onsubmit="return confirm('Batalkan event ini? Data pendaftaran tetap tersimpan.')">
        @csrf
        <button type="submit" style="background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; padding:9px 20px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer;">Batalkan Event</button>
    </form>
    @endif
</div>
@endsection
