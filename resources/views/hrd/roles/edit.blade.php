@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl p-4 sm:p-6">
  <div class="rounded-2xl border bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Edit Role</h1>

    <form method="POST" action="{{ route('hrd.roles.update', $role) }}" class="mt-5 space-y-4">
      @csrf
      @method('PUT')
      <div>
        <label class="mb-1 block text-sm font-medium">Nama</label>
        <input type="text" name="name" value="{{ old('name', $role->name) }}" required class="w-full rounded-lg border-gray-300">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium">Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $role->slug) }}" required @readonly($role->is_system || $role->is_super_admin) class="w-full rounded-lg border-gray-300">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium">Deskripsi</label>
        <textarea name="description" rows="3" class="w-full rounded-lg border-gray-300">{{ old('description', $role->description) }}</textarea>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Update</button>
        <a href="{{ route('hrd.roles.index') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">Kembali</a>
      </div>
    </form>
  </div>
</div>
@endsection
