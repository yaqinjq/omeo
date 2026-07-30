@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4">
  <div class="text-lg font-semibold mb-3">Tambah Kandidat</div>
  <form method="POST" action="{{ route('candidates.store') }}">
    @csrf
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
  <div>
    <label class="text-sm">Nama *</label>
    <input name="full_name" value="{{ old('full_name', $candidate->full_name ?? '') }}" class="w-full border rounded p-2" required>
  </div>
  <div>
    <label class="text-sm">NIK</label>
    <input name="nik" value="{{ old('nik', $candidate->nik ?? '') }}" class="w-full border rounded p-2">
  </div>
  <div>
    <label class="text-sm">Email</label>
    <input type="email" name="email" value="{{ old('email', $candidate->email ?? '') }}" class="w-full border rounded p-2">
  </div>
  <div>
    <label class="text-sm">Phone</label>
    <input name="phone" value="{{ old('phone', $candidate->phone ?? '') }}" class="w-full border rounded p-2">
  </div>
  <div class="md:col-span-2">
    <label class="text-sm">Catatan</label>
    <textarea name="notes" class="w-full border rounded p-2" rows="3">{{ old('notes', $candidate->notes ?? '') }}</textarea>
  </div>
  @if(isset($candidate))
  <div>
    <label class="text-sm">Status</label>
    <select name="status" class="w-full border rounded p-2">
      @php($v=old('status',$candidate->status))
      @foreach(['applied','shortlisted','accepted','rejected','blocked'] as $s)
        <option value="{{ $s }}" @selected($v===$s)>{{ $s }}</option>
      @endforeach
    </select>
  </div>
  @endif
</div>

    <div class="mt-4 flex gap-2">
      <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan</button>
      <a class="px-4 py-2 rounded border" href="{{ route('candidates.index') }}">Kembali</a>
    </div>
  </form>
</div>
@endsection
