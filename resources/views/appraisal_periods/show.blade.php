@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4">
  <div class="text-lg font-semibold">{{ $period->name }}</div>
  <div class="text-sm text-slate-600">{{ $period->type }} • {{ $period->start_date->format('d-m-Y') }} - {{ $period->end_date->format('d-m-Y') }}</div>
</div>
@endsection
