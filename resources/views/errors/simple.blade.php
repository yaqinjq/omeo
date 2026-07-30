@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-2xl p-6">
  <h1 class="text-xl font-semibold mb-2">{{ $title ?? 'Error' }}</h1>
  <p class="text-slate-600">{{ $message ?? 'Terjadi kesalahan.' }}</p>
</div>
@endsection
