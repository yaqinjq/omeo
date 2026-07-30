@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4">
  <div class="text-lg font-semibold mb-3">Tambah Periode</div>
  <form method="POST" action="{{ route('appraisal-periods.store') }}">
    @csrf
    <div class="grid grid-cols-1 gap-3">
  <div>
    <label class="text-sm">Nama *</label>
    <input name="name" value="{{ old('name', $period->name ?? '') }}" class="w-full border rounded p-2" required>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div>
      <label class="text-sm">Start Date *</label>
      <input type="date" name="start_date" value="{{ old('start_date', isset($period) ? $period->start_date->format('Y-m-d') : '') }}" class="w-full border rounded p-2" required>
    </div>
    <div>
      <label class="text-sm">End Date *</label>
      <input type="date" name="end_date" value="{{ old('end_date', isset($period) ? $period->end_date->format('Y-m-d') : '') }}" class="w-full border rounded p-2" required>
    </div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div>
      <label class="text-sm">Type *</label>
      @php($v=old('type', $period->type ?? 'probation'))
      <select name="type" class="w-full border rounded p-2" required>
        @foreach(['probation','annual','contract_renewal'] as $t)
          <option value="{{ $t }}" @selected($v===$t)>{{ $t }}</option>
        @endforeach
      </select>
    </div>
    <div class="flex items-center gap-2 mt-6">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $period->is_active ?? true))>
      <span class="text-sm">Active</span>
    </div>
  </div>
</div>

    <div class="mt-4 flex gap-2">
      <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan</button>
      <a class="px-4 py-2 rounded border" href="{{ route('appraisal-periods.index') }}">Kembali</a>
    </div>
  </form>
</div>
@endsection
