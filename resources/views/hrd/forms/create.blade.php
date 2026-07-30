@extends('layouts.app')

@section('content')
<div class="bg-white border rounded-lg p-4 space-y-4">
  @if(!empty($moduleWarning))
    <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $moduleWarning }}</div>
  @endif
  <div>
    <h1 class="text-lg font-semibold">Buat Form Dinamis</h1>
    <p class="text-sm text-slate-600">Builder ini mendukung IQ, DISC, TIU, Diferensial, FAT, dan Custom.</p>
  </div>

  <form method="POST" action="{{ route('hrd.forms.store') }}" class="space-y-3">
    @csrf
    <div>
      <label class="text-sm">Nama Form</label>
      <input type="text" name="name" value="{{ old('name') }}" class="w-full border rounded p-2" required>
    </div>
    <div>
      <label class="text-sm">Deskripsi</label>
      <textarea name="description" class="w-full border rounded p-2" rows="3">{{ old('description') }}</textarea>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <div>
        <label class="text-sm">Tipe</label>
        <select name="type" class="w-full border rounded p-2">
          @foreach(($builderTypes ?? []) as $key => $label)
            <option value="{{ $key }}" @selected(old('type', 'iq') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-sm">Departemen Audience</label>
        <select name="department_id" class="w-full border rounded p-2">
          <option value="">Umum / Semua Departemen</option>
          @foreach(($departments ?? collect()) as $department)
            <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-sm">Durasi (menit)</label>
        <input type="number" min="1" max="240" name="duration_minutes" value="{{ old('duration_minutes') }}" class="w-full border rounded p-2">
      </div>
    </div>
    <label class="inline-flex items-center gap-2 text-sm">
      <input type="checkbox" name="is_active" value="1" checked>
      Aktifkan form sekarang
    </label>

    <div class="rounded border bg-slate-50 p-3 text-sm text-slate-600">
      Untuk DISC, builder akan memakai konfigurasi dual-axis pada opsi jawaban. Untuk IQ, TIU, Diferensial, dan FAT, builder memakai pola pilihan berbobot agar import dan scoring tetap reusable.
    </div>

    <div class="flex gap-2">
      <button class="px-4 py-2 rounded bg-slate-900 text-white disabled:opacity-50" @disabled(!($schemaReady ?? true))>Simpan</button>
      <a href="{{ route('hrd.forms.index') }}" class="px-4 py-2 rounded border">Kembali</a>
    </div>
  </form>
</div>
@endsection
