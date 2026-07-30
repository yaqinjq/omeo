@extends('layouts.app')
@section('content')
<div class="max-w-2xl mx-auto space-y-4">
  <div>
    <h1 class="text-2xl font-semibold">{{ $item->exists ? 'Edit Akun BPJS Legal' : 'Tambah Akun BPJS Legal' }}</h1>
  </div>

  @if($errors->any())
    <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
      <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST"
        action="{{ $item->exists ? route('finance.bpjs-legal.update', $item) : route('finance.bpjs-legal.store') }}"
        class="bg-white rounded-lg border p-6 space-y-4">
    @csrf
    @if($item->exists) @method('PUT') @endif

    <div class="grid gap-4 md:grid-cols-2">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Entitas Legal <span class="text-red-500">*</span></label>
        <select name="legal_entity_id" class="border rounded px-3 py-2 w-full @error('legal_entity_id') border-red-400 @enderror" required>
          <option value="">-- Pilih Entitas --</option>
          @foreach($legalEntities as $le)
            <option value="{{ $le->id }}" @selected(old('legal_entity_id', $item->legal_entity_id) == $le->id)>
              {{ $le->name }}@if($le->short_name) ({{ $le->short_name }})@endif
            </option>
          @endforeach
        </select>
        @error('legal_entity_id')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Tipe BPJS <span class="text-red-500">*</span></label>
        <select name="bpjs_type" class="border rounded px-3 py-2 w-full" required>
          <option value="ketenagakerjaan" @selected(old('bpjs_type', $item->bpjs_type) === 'ketenagakerjaan')>BPJS Ketenagakerjaan</option>
          <option value="kesehatan" @selected(old('bpjs_type', $item->bpjs_type) === 'kesehatan')>BPJS Kesehatan</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">No. Kepesertaan <span class="text-red-500">*</span></label>
        <input type="text" name="account_number" value="{{ old('account_number', $item->account_number) }}" required
               class="border rounded px-3 py-2 w-full font-mono @error('account_number') border-red-400 @enderror"
               placeholder="12345678">
        @error('account_number')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
      </div>
      <div class="md:col-span-2">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">
              NPP (Nomor Pendaftaran Perusahaan)
              <span class="text-gray-400 text-xs font-normal">(opsional)</span>
            </label>
            <input type="text" name="npp"
                   value="{{ old('npp', $item->npp ?? '') }}"
                   placeholder="Contoh: 17081342"
                   maxlength="20"
                   class="border border-gray-300 rounded px-3 py-2 w-full text-sm
                          focus:ring-2 focus:ring-indigo-300">
            <p class="text-xs text-gray-400 mt-1">
              Tertera di Formulir 2a PU dari BPJS Ketenagakerjaan.
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">
              Upah Basis Perhitungan (Rp)
              <span class="text-gray-400 text-xs font-normal">(opsional)</span>
            </label>
            <input type="number" name="upah_basis_perhitungan"
                   value="{{ old('upah_basis_perhitungan', $item->upah_basis_perhitungan ?? '') }}"
                   placeholder="Contoh: 3500000"
                   class="border border-gray-300 rounded px-3 py-2 w-full text-sm
                          focus:ring-2 focus:ring-indigo-300">
            <p class="text-xs text-gray-400 mt-1">
              UMR yang digunakan sebagai basis perhitungan iuran.
            </p>
          </div>
        </div>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Nama Peserta Terdaftar</label>
        <input type="text" name="account_name" value="{{ old('account_name', $item->account_name) }}"
               class="border rounded px-3 py-2 w-full" placeholder="PT Omeo Nusantara">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Kantor Cabang BPJS</label>
        <input type="text" name="bpjs_branch" value="{{ old('bpjs_branch', $item->bpjs_branch) }}"
               class="border rounded px-3 py-2 w-full" placeholder="BPJS TK Jakarta Pusat">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Tanggal Daftar</label>
        <input type="date" name="registered_date"
               value="{{ old('registered_date', $item->registered_date?->format('Y-m-d')) }}"
               class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="is_active" class="border rounded px-3 py-2 w-full">
          <option value="1" @selected(old('is_active', $item->is_active ?? true))>Aktif</option>
          <option value="0" @selected(!old('is_active', $item->is_active ?? true))>Nonaktif</option>
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Catatan</label>
        <textarea name="notes" rows="2" class="border rounded px-3 py-2 w-full">{{ old('notes', $item->notes) }}</textarea>
      </div>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="px-5 py-2 rounded bg-gray-900 text-white text-sm">Simpan</button>
      <a href="{{ route('finance.bpjs-legal.index') }}" class="px-5 py-2 rounded border text-sm">Batal</a>
    </div>
  </form>
</div>
@endsection
