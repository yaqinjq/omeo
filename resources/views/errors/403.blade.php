@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl p-6">
  <div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-rose-700">Akses Ditolak (403)</h1>
    <p class="mt-2 text-sm text-rose-700">
      {{ trim((string) ($exception->getMessage() ?? '')) !== '' ? $exception->getMessage() : 'Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.' }}
    </p>
    <div class="mt-4">
      <a href="{{ url()->previous() }}" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Kembali</a>
    </div>
  </div>
</div>
@endsection
