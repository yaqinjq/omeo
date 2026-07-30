@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4 max-w-2xl">
  <div class="text-lg font-semibold mb-3">Template Kriteria Baru</div>
  <form method="POST" action="{{ route('appraisal-criteria-templates.store') }}">
    @csrf
    <div class="grid grid-cols-1 gap-3">
      <div>
        <label class="text-sm">Nama Template *</label>
        <input name="name" value="{{ old('name') }}" class="w-full border rounded p-2" required placeholder="Contoh: Kriteria Operational Outlet">
      </div>
      <div>
        <label class="text-sm">Deskripsi (opsional)</label>
        <textarea name="description" rows="2" class="w-full border rounded p-2">{{ old('description') }}</textarea>
      </div>
      <div>
        <label class="text-sm">Kategori Karyawan</label>
        <select name="lokasi_kerja" class="w-full border rounded p-2">
          <option value="">— Tidak otomatis (pilih manual saja) —</option>
          @foreach(\App\Models\AppraisalCriteriaTemplate::LOKASI_KERJA_OPTIONS as $val => $label)
            <option value="{{ $val }}" @selected(old('lokasi_kerja') === $val)>{{ $label }}</option>
          @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Jika dipilih, template ini otomatis dipakai untuk karyawan dengan kategori lokasi kerja yang sama.</p>
      </div>
      <div class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" id="is_active" checked class="rounded border-slate-300">
        <label for="is_active" class="text-sm">Aktif</label>
      </div>
    </div>

    <div class="mt-4 flex gap-2">
      <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan &amp; Lanjut Isi Kriteria</button>
      <a class="px-4 py-2 rounded border" href="{{ route('appraisal-criteria-templates.index') }}">Kembali</a>
    </div>
  </form>
</div>
@endsection
