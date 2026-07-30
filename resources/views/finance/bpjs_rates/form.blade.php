@extends('layouts.app')
@section('content')
<div class="max-w-2xl mx-auto space-y-4">
  <div>
    <h1 class="text-2xl font-semibold">{{ $item->exists ? 'Edit Tarif BPJS' : 'Tambah Konfigurasi Tarif BPJS' }}</h1>
    <p class="text-sm text-slate-500 mt-1">Masukkan persentase sebagai desimal (contoh: 3.70% → isi 0.037)</p>
  </div>

  @if($errors->any())
    <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
      <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST"
        action="{{ $item->exists ? route('finance.bpjs-rates.update', $item) : route('finance.bpjs-rates.store') }}"
        class="bg-white rounded-lg border p-6 space-y-6">
    @csrf
    @if($item->exists) @method('PUT') @endif

    <div class="grid gap-4 md:grid-cols-2">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Regional</label>
        <select name="region_id" class="border rounded px-3 py-2 w-full">
          <option value="">-- Berlaku untuk Semua Regional --</option>
          @foreach($regions as $r)
            <option value="{{ $r->id }}" @selected(old('region_id', $item->region_id) == $r->id)>
              {{ $r->name }}@if($r->legalEntity) — {{ $r->legalEntity->short_name ?? $r->legalEntity->name }}@endif
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Berlaku Mulai (YYYY-MM) <span class="text-red-500">*</span></label>
        <input type="text" name="periode_mulai" value="{{ old('periode_mulai', $item->periode_mulai) }}" required
               class="border rounded px-3 py-2 w-full font-mono @error('periode_mulai') border-red-400 @enderror"
               placeholder="2025-01" pattern="\d{4}-\d{2}">
        @error('periode_mulai')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Berlaku s/d (YYYY-MM)</label>
        <input type="text" name="periode_selesai" value="{{ old('periode_selesai', $item->periode_selesai) }}"
               class="border rounded px-3 py-2 w-full font-mono" placeholder="Kosongkan = berlaku sekarang" pattern="\d{4}-\d{2}">
      </div>
    </div>

    <div>
      <div class="text-sm font-semibold text-slate-700 mb-3 border-b pb-2">BPJS Ketenagakerjaan</div>
      <div class="grid gap-4 md:grid-cols-3">
        <div>
          <label class="block text-xs font-medium mb-1">JKK Rate</label>
          <input type="number" name="jkk_rate" value="{{ old('jkk_rate', $item->jkk_rate ?? 0.0054) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" step="0.0001" min="0" max="1">
          <p class="text-xs text-slate-400 mt-1">F&amp;B/Restoran: 0.0054 (0.54%)</p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">JKM Rate</label>
          <input type="number" name="jkm_rate" value="{{ old('jkm_rate', $item->jkm_rate ?? 0.0030) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" step="0.0001" min="0" max="1">
          <p class="text-xs text-slate-400 mt-1">Default: 0.003 (0.30%)</p>
        </div>
        <div></div>
        <div>
          <label class="block text-xs font-medium mb-1">JHT Perusahaan</label>
          <input type="number" name="jht_employer_rate" value="{{ old('jht_employer_rate', $item->jht_employer_rate ?? 0.0370) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" step="0.0001" min="0" max="1">
          <p class="text-xs text-slate-400 mt-1">Default: 0.037 (3.70%)</p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">JHT Karyawan</label>
          <input type="number" name="jht_employee_rate" value="{{ old('jht_employee_rate', $item->jht_employee_rate ?? 0.0200) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" step="0.0001" min="0" max="1">
          <p class="text-xs text-slate-400 mt-1">Default: 0.02 (2.00%)</p>
        </div>
        <div></div>
        <div>
          <label class="block text-xs font-medium mb-1">JP Perusahaan</label>
          <input type="number" name="jp_employer_rate" value="{{ old('jp_employer_rate', $item->jp_employer_rate ?? 0.0200) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" step="0.0001" min="0" max="1">
          <p class="text-xs text-slate-400 mt-1">Default: 0.02 (2.00%)</p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">JP Karyawan</label>
          <input type="number" name="jp_employee_rate" value="{{ old('jp_employee_rate', $item->jp_employee_rate ?? 0.0100) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" step="0.0001" min="0" max="1">
          <p class="text-xs text-slate-400 mt-1">Default: 0.01 (1.00%)</p>
        </div>
      </div>
    </div>

    <div>
      <div class="text-sm font-semibold text-slate-700 mb-3 border-b pb-2">BPJS Kesehatan</div>
      <div class="grid gap-4 md:grid-cols-3">
        <div>
          <label class="block text-xs font-medium mb-1">BPJS Kes Perusahaan</label>
          <input type="number" name="bpjskes_employer_rate" value="{{ old('bpjskes_employer_rate', $item->bpjskes_employer_rate ?? 0.0400) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" step="0.0001" min="0" max="1">
          <p class="text-xs text-slate-400 mt-1">Default: 0.04 (4.00%)</p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">BPJS Kes Karyawan</label>
          <input type="number" name="bpjskes_employee_rate" value="{{ old('bpjskes_employee_rate', $item->bpjskes_employee_rate ?? 0.0100) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" step="0.0001" min="0" max="1">
          <p class="text-xs text-slate-400 mt-1">Default: 0.01 (1.00%)</p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Batas Upah Maksimum</label>
          <input type="number" name="bpjskes_salary_cap" value="{{ old('bpjskes_salary_cap', $item->bpjskes_salary_cap) }}"
                 class="border rounded px-3 py-2 w-full font-mono text-sm" min="0" step="100000"
                 placeholder="Kosongkan = tidak ada batas">
        </div>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="is_active" class="border rounded px-3 py-2 w-full">
          <option value="1" @selected(old('is_active', $item->is_active ?? true))>Aktif</option>
          <option value="0" @selected(!old('is_active', $item->is_active ?? true))>Nonaktif</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Catatan</label>
        <textarea name="notes" rows="2" class="border rounded px-3 py-2 w-full">{{ old('notes', $item->notes) }}</textarea>
      </div>
    </div>

    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">
            Basis Perhitungan Iuran
        </label>
        <select name="basis_perhitungan"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                       focus:ring-2 focus:ring-indigo-300">
            <option value="umr"
                {{ old('basis_perhitungan', $item->basis_perhitungan ?? 'umr') === 'umr' ? 'selected' : '' }}>
                UMR / Upah Minimum Regional
            </option>
            <option value="gaji_aktual"
                {{ old('basis_perhitungan', $item->basis_perhitungan ?? '') === 'gaji_aktual' ? 'selected' : '' }}>
                Gaji Aktual Karyawan
            </option>
            <option value="capped_umr"
                {{ old('basis_perhitungan', $item->basis_perhitungan ?? '') === 'capped_umr' ? 'selected' : '' }}>
                Dibatasi Maksimal UMR
            </option>
        </select>
        <p class="text-xs text-gray-400 mt-1">
            Pilih dasar upah yang digunakan untuk menghitung besaran iuran BPJS.
        </p>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="px-5 py-2 rounded bg-gray-900 text-white text-sm">Simpan</button>
      <a href="{{ route('finance.bpjs-rates.index') }}" class="px-5 py-2 rounded border text-sm">Batal</a>
    </div>
  </form>
</div>
@endsection
