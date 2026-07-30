@extends('layouts.app')

@section('page_title', $event->exists ? 'Edit Walk In' : 'Tambah Walk In')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $event->exists ? 'Edit Event Walk In' : 'Tambah Event Walk In' }}</h1>
    <p class="mt-1 text-sm text-muted">Event published akan tampil di landing page dan halaman publik /walk-in.</p>
  </div>

  <form method="POST" action="{{ $event->exists ? route('dashboard.walk-ins.update', $event) : route('dashboard.walk-ins.store') }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if($event->exists)
      @method('PUT')
    @endif

    <div class="card p-5">
      <div class="grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-semibold">Judul Event
          <input name="title" value="{{ old('title', $event->title) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
        </label>
        <label class="block text-sm font-semibold">Slug
          <input name="slug" value="{{ old('slug', $event->slug) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" placeholder="otomatis jika kosong">
        </label>
        <label class="block text-sm font-semibold">Status
          <select name="status" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
            @foreach(\App\Models\WalkInEvent::STATUSES as $status)
              <option value="{{ $status }}" @selected(old('status', $event->status) === $status)>{{ ucfirst($status) }}</option>
            @endforeach
          </select>
        </label>
        <label class="block text-sm font-semibold">Tanggal Event
          <input type="date" name="event_date" value="{{ old('event_date', optional($event->event_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required>
        </label>
        <label class="block text-sm font-semibold">Jam Mulai
          <input type="time" name="start_time" value="{{ old('start_time', $event->start_time ? substr($event->start_time, 0, 5) : '') }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Jam Selesai
          <input type="time" name="end_time" value="{{ old('end_time', $event->end_time ? substr($event->end_time, 0, 5) : '') }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Lokasi Singkat
          <input name="location" value="{{ old('location', $event->location) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold">Kuota Peserta
          <input type="number" min="1" name="quota" value="{{ old('quota', $event->quota) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
        </label>
        <label class="block text-sm font-semibold md:col-span-2">Alamat Lengkap
          <textarea name="address" rows="3" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('address', $event->address) }}</textarea>
        </label>
        <label class="block text-sm font-semibold md:col-span-2">Google Maps URL
          <input name="maps_url" value="{{ old('maps_url', $event->maps_url) }}" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" placeholder="https://maps.google.com/...">
        </label>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">Konten Event</h2>
      <div class="mt-4 grid gap-4">
        <label class="block text-sm font-semibold">Posisi Dibuka
          <textarea name="positions_text" rows="8" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50" required placeholder="Barista&#10;Kasir&#10;Waiter/Waitress">{{ old('positions_text', $positionsText) }}</textarea>
          <span class="text-xs text-muted">Satu posisi per baris. Posisi lama yang tidak ada di daftar akan dinonaktifkan.</span>
        </label>
        <label class="block text-sm font-semibold">Deskripsi Event
          <textarea name="description" rows="5" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('description', $event->description) }}</textarea>
        </label>
        <label class="block text-sm font-semibold">Catatan / Instruksi Peserta
          <textarea name="participant_instruction" rows="4" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">{{ old('participant_instruction', $event->participant_instruction) }}</textarea>
        </label>
        <div>
          <label class="block text-sm font-semibold">Banner Event</label>
          @if($event->banner_url)
            <img src="{{ $event->banner_url }}" alt="{{ $event->title }}" class="mt-2 h-32 w-auto rounded-xl object-cover">
          @endif
          <input type="file" name="banner" accept="image/*" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        </div>
      </div>
    </div>

    <div class="flex justify-end gap-3">
      <a href="{{ route('dashboard.walk-ins.index') }}" class="btn-outline">Batal</a>
      <button class="btn-primary" type="submit">Simpan Event</button>
    </div>
  </form>
</div>
@endsection
