@extends('layouts.app')
@section('content')
@php $isEdit = $mode === 'edit'; @endphp

<div class="bg-white border rounded-2xl p-6 max-w-3xl">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">{{ $isEdit ? 'Edit' : 'Tambah' }} Materi</h1>
    <a href="{{ route('training-materials.index') }}" class="px-3 py-2 rounded-lg border hover:bg-slate-50">Kembali</a>
  </div>

  <form method="POST" action="{{ $isEdit ? route('training-materials.update',$row->id) : route('training-materials.store') }}" class="mt-6 space-y-4">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div>
      <label class="text-sm font-medium">Title</label>
      <input name="title" value="{{ old('title', $row->title ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
    </div>

    <div>
      <label class="text-sm font-medium">Category</label>
      <input name="category" value="{{ old('category', $row->category ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2" required>
    </div>

    <div>
      <label class="text-sm font-medium">YouTube URL</label>
      <input name="youtube_url" value="{{ old('youtube_url', $row->youtube_url ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
    </div>

    <div>
      <label class="text-sm font-medium">Duration minutes</label>
      <input name="duration_minutes" type="number" value="{{ old('duration_minutes', $row->duration_minutes ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2">
    </div>

    <div>
      <label class="text-sm font-medium">Description</label>
      <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border px-3 py-2">{{ old('description', $row->description ?? '') }}</textarea>
    </div>

    <button class="px-4 py-2 rounded-lg bg-slate-900 text-white">{{ $isEdit ? 'Update' : 'Simpan' }}</button>
  </form>
</div>
@endsection
