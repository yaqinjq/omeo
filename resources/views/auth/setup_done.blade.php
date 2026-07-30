@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-2xl p-6 max-w-lg mx-auto">
  <h1 class="text-xl font-semibold mb-2">Setup tidak diperlukan</h1>
  <p class="text-slate-600">Tabel users sudah berisi data. Silakan login.</p>
  <div class="mt-4">
    <a class="text-blue-600 hover:underline" href="{{ route('login') }}">Ke halaman login</a>
  </div>
</div>
@endsection
