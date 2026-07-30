@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4">
  <div class="flex items-center justify-between mb-3">
    <div>
      <div class="text-lg font-semibold">Preview Offering</div>
      <div class="text-sm text-slate-600">{{ $employee->full_name }} ({{ $employee->nik }})</div>
    </div>
    <form method="POST" action="{{ route('offering.generate', $employee) }}">
      @csrf
      <button class="px-3 py-2 rounded bg-slate-900 text-white">Generate</button>
    </form>
  </div>
  <div class="border rounded p-3 bg-white">
    {!! $html !!}
  </div>
</div>
@endsection
