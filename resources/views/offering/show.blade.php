@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-2xl p-6">
  <h1 class="text-2xl font-semibold">Offering Document</h1>
  <p class="text-slate-500 text-sm mt-1">Output disimpan sebagai HTML agar project tetap jalan tanpa dependency PDF.</p>

  <div class="mt-4">
    <a class="px-3 py-2 rounded-lg bg-slate-900 text-white" href="{{ $url }}" target="_blank">Open / Print</a>
  </div>
</div>
@endsection
