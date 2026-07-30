@extends('layouts.app')

@section('page_title', $member->exists ? 'Edit Tim HR' : 'Tambah Tim HR')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $member->exists ? 'Edit Anggota Tim HR' : 'Tambah Anggota Tim HR' }}</h1>
    <p class="mt-1 text-sm text-muted">Email yang dipakai adalah email company, bukan data karyawan.</p>
  </div>

  <form method="POST" action="{{ $member->exists ? route('dashboard.hr-team.update', $member) : route('dashboard.hr-team.store') }}" enctype="multipart/form-data" class="card p-5">
    @csrf
    @if($member->exists)
      @method('PUT')
    @endif

    <div class="grid gap-4 md:grid-cols-2">
      <label class="block text-sm font-semibold">Nama
        <input name="name" value="{{ old('name', $member->name) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
      </label>
      <label class="block text-sm font-semibold">Jabatan
        <input name="position" value="{{ old('position', $member->position) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
      </label>
      <label class="block text-sm font-semibold">Email Company
        <input type="email" name="company_email" value="{{ old('company_email', $member->company_email) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
      </label>
      <label class="block text-sm font-semibold">Urutan Tampil
        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $member->sort_order ?? 0) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
      </label>
      <div>
        <label class="block text-sm font-semibold">Foto</label>
        @if($member->photo_url)
          <img src="{{ $member->photo_url }}" class="mt-2 h-24 w-24 rounded-2xl object-cover" alt="{{ $member->name }}">
        @endif
        <input type="file" name="photo" accept="image/*" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
      </div>
      <label class="flex items-center gap-3 self-end rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold dark:border-slate-700">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $member->is_active)) class="rounded border-slate-300">
        Aktif tampil di landing page
      </label>
    </div>

    <div class="mt-6 flex justify-end gap-3">
      <a href="{{ route('dashboard.hr-team.index') }}" class="btn-outline">Batal</a>
      <button class="btn-primary" type="submit">Simpan</button>
    </div>
  </form>
</div>
@endsection
