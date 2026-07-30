@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-2xl p-6">
  <h1 class="text-xl font-semibold mb-2">Tabel belum tersedia</h1>
  <p class="text-slate-600">Sistem mencoba akses tabel: <code>{{ $table }}</code> tetapi tidak ditemukan di database.</p>
  <p class="text-slate-600 mt-2">Pastikan import SQL dump yang benar dan DB_DATABASE di .env mengarah ke database itu.</p>
</div>
@endsection
