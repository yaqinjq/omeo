@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl p-4 sm:p-6">
  <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Buat Role</h1>
    <p class="mb-5 text-sm text-gray-500">Slug harus lowercase dengan underscore atau dash.</p>

    <form method="POST" action="{{ route('hrd.roles.store') }}" class="space-y-4">
      @csrf
      <div>
        <label class="mb-1 block text-sm font-medium">Nama</label>
        <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-gray-300">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium">Slug</label>
        <input type="text" name="slug" value="{{ old('slug') }}" required class="w-full rounded-lg border-gray-300">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium">Deskripsi</label>
        <textarea name="description" rows="3" class="w-full rounded-lg border-gray-300">{{ old('description') }}</textarea>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Simpan</button>
        <a href="{{ route('hrd.roles.index') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection
