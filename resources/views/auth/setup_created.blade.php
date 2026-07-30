@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-2xl p-6 max-w-lg mx-auto">
  <h1 class="text-xl font-semibold mb-2">Setup berhasil</h1>
  <p class="text-slate-600 mb-4">Admin pertama berhasil dibuat.</p>
  <div class="rounded-lg bg-slate-50 border p-4 text-sm">
    <div><b>Email:</b> {{ $email }}</div>
    <div><b>Password:</b> {{ $password }}</div>
  </div>
  <div class="mt-4">
    <a class="text-blue-600 hover:underline" href="{{ route('login') }}">Lanjut login</a>
  </div>
</div>
@endsection
