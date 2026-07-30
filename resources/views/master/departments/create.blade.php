@extends('layouts.app')
@section('content')
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-semibold mb-4">Tambah Departemen</h1>
  <form method="POST" action="{{ route('departments.store') }}" class="bg-white border rounded-lg p-4 space-y-4">
    @csrf
    <div>
  <label class="block text-sm text-gray-600">Code</label>
  <input name="code" value="{{ old('code') }}" class="border rounded px-3 py-2 w-full">
  @error('code')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
</div>

    <div>
      <label class="block text-sm text-gray-600">Nama</label>
      <input name="name" value="{{ old('name') }}" class="border rounded px-3 py-2 w-full">
      @error('name')<div class="text-red-600 text-sm">{{ $message }}</div>@enderror
    </div>
    <div class="flex gap-2">
      <button class="px-4 py-2 rounded bg-gray-900 text-white">Simpan</button>
      <a href="{{ route('departments.index') }}" class="px-4 py-2 rounded border">Kembali</a>
    </div>
  </form>
</div>
@endsection
