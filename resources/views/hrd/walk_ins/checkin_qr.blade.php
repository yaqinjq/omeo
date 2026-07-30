@extends('layouts.app')

@section('page_title', 'QR Check-in Walk In')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">QR Check-in: {{ $event->title }}</h1>
        <p class="mt-1 text-sm text-muted">Token berubah setiap halaman ini direfresh dan berlaku sampai {{ $expiresAt->format('H:i:s') }}.</p>
      </div>
      <a href="{{ route('dashboard.walk-ins.participants', $event) }}" class="btn-outline">Peserta</a>
    </div>
  </div>

  <div class="card p-6 text-center">
    @if($event->isCheckinOpen())
      <img class="mx-auto h-72 w-72 rounded-3xl border border-slate-200 bg-white p-4" alt="QR check-in" src="https://api.qrserver.com/v1/create-qr-code/?size=320x320&data={{ urlencode($checkinUrl) }}">
      <div class="mt-4 break-all text-sm font-semibold">{{ $checkinUrl }}</div>
      <p class="mt-3 text-sm text-muted">Tampilkan halaman ini di lokasi event. Peserta tetap harus memasukkan kode registrasi atau nomor WhatsApp setelah scan.</p>
      <a href="{{ route('dashboard.walk-ins.checkin-qr', $event) }}" class="btn-primary mt-5">Refresh Token</a>
    @else
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">Window check-in belum aktif atau event tidak published.</div>
    @endif
  </div>
</div>
@endsection
