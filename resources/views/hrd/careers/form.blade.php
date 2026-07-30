@extends('layouts.app')

@section('page_title', $post->exists ? 'Edit Lowongan' : 'Tambah Lowongan')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $post->exists ? 'Edit Lowongan' : 'Tambah Lowongan' }}</h1>
    <p class="mt-1 text-sm text-muted">Lowongan published akan tampil di landing page dan halaman /karir.</p>
  </div>

  <form method="POST" action="{{ $post->exists ? route('dashboard.careers.update', $post) : route('dashboard.careers.store') }}" class="space-y-5">
    @csrf
    @if($post->exists)
      @method('PUT')
    @endif

    <div class="card p-5">
      <div class="grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">Judul Lowongan
          <input name="title" value="{{ old('title', $post->title) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
        </label>
        <label class="block text-sm font-semibold">Slug
          <input name="slug" value="{{ old('slug', $post->slug) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" placeholder="otomatis jika kosong">
        </label>
        <label class="block text-sm font-semibold">Departemen Existing
          <select name="career_department_id" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
            <option value="">Buat / pakai nama departemen di bawah</option>
            @foreach($departments as $department)
              <option value="{{ $department->id }}" @selected((int) old('career_department_id', $post->career_department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
          </select>
        </label>
        <label class="block text-sm font-semibold">Nama Departemen Baru
          <input name="department_name" value="{{ old('department_name') }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" placeholder="Contoh: Human Resource">
        </label>
        <label class="block text-sm font-semibold">Penempatan / Lokasi
          <input name="location" value="{{ old('location', $post->location) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Tipe Kerja
          <select name="employment_type" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
            @foreach(\App\Models\CareerPost::EMPLOYMENT_TYPES as $type)
              <option value="{{ $type }}" @selected(old('employment_type', $post->employment_type) === $type)>{{ $type }}</option>
            @endforeach
          </select>
        </label>
        <label class="block text-sm font-semibold">Status
          <select name="status" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
            @foreach(\App\Models\CareerPost::STATUSES as $status)
              <option value="{{ $status }}" @selected(old('status', $post->status) === $status)>{{ ucfirst($status) }}</option>
            @endforeach
          </select>
        </label>
        <label class="block text-sm font-semibold">Tanggal Publish
          <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($post->published_at)->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Tanggal Closing
          <input type="date" name="closing_at" value="{{ old('closing_at', optional($post->closing_at)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">Konten Lowongan</h2>
      <div class="mt-4 grid gap-4">
        <label class="block text-sm font-semibold">Deskripsi Pekerjaan
          <textarea name="description" rows="6" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('description', $post->description) }}</textarea>
        </label>
        <label class="block text-sm font-semibold">Kualifikasi
          <textarea name="qualifications" rows="6" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('qualifications', $post->qualifications) }}</textarea>
        </label>
        <label class="block text-sm font-semibold">Benefit
          <textarea name="benefits" rows="4" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('benefits', $post->benefits) }}</textarea>
        </label>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">SEO & Apply</h2>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">SEO Title
          <input name="seo_title" value="{{ old('seo_title', $post->seo_title) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Label Tombol Apply
          <input name="apply_button_label" value="{{ old('apply_button_label', $post->apply_button_label ?: 'Lamar Posisi') }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold md:col-span-2">SEO Description
          <input name="seo_description" value="{{ old('seo_description', $post->seo_description) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold md:col-span-2">Apply URL
          <input name="apply_url" value="{{ old('apply_url', $post->apply_url) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" placeholder="{{ route('register') }}">
        </label>
      </div>
    </div>

    <div class="flex justify-end gap-3">
      <a href="{{ route('dashboard.careers.index') }}" class="btn-outline">Batal</a>
      <button class="btn-primary" type="submit">Simpan Lowongan</button>
    </div>
  </form>
</div>
@endsection
