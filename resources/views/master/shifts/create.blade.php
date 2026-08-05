@extends('layouts.app')
@section('content')
<div class="max-w-lg mx-auto space-y-4">
  <h1 class="text-2xl font-semibold">Tambah Shift</h1>

  @if($errors->any())
    <div class="rounded-lg border border-rose-300 bg-rose-50 p-3 text-sm text-rose-800">
      <ul class="list-disc pl-5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('master-shifts.store') }}" class="bg-white border rounded-lg p-5 space-y-4">
    @csrf
    <div>
      <label class="block text-sm text-slate-600 mb-1">Outlet Operational</label>
      <select name="outlet_id" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
        <option value="">— Pilih Outlet —</option>
        @foreach($outlets as $o)
          <option value="{{ $o->id }}" @selected(old('outlet_id', request('outlet_id')) == $o->id)>{{ $o->name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="block text-sm text-slate-600 mb-1">Kode Shift (unik per outlet)</label>
      <input type="text" name="code" value="{{ old('code') }}" placeholder="mis. PAGI, SIANG, MALAM" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
    </div>
    <div>
      <label class="block text-sm text-slate-600 mb-1">Nama Shift</label>
      <input type="text" name="name" value="{{ old('name') }}" placeholder="mis. Shift Pagi" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm text-slate-600 mb-1">Jam Masuk</label>
        <input type="time" name="in_time" value="{{ old('in_time') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm text-slate-600 mb-1">Jam Pulang</label>
        <input type="time" name="out_time" value="{{ old('out_time') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
      </div>
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
      Aktif
    </label>
    <div class="flex gap-2">
      <button class="px-4 py-2 rounded bg-gray-900 text-white">Simpan</button>
      <a href="{{ route('master-shifts.index') }}" class="px-4 py-2 rounded border">Batal</a>
    </div>
  </form>
</div>
@endsection
