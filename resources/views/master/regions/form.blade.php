@extends('layouts.app')
@section('content')
<div class="max-w-2xl mx-auto space-y-4">
  <div>
    <h1 class="text-2xl font-semibold">{{ $item->exists ? 'Edit Regional' : 'Tambah Regional' }}</h1>
  </div>

  @if($errors->any())
    <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
      <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form id="main_form"
        method="POST"
        action="{{ $item->exists ? route('master.regions.update', $item) : route('master.regions.store') }}"
        class="bg-white rounded-lg border p-6 space-y-4">
    @csrf
    @if($item->exists) @method('PUT') @endif

    <div class="grid gap-4 md:grid-cols-2">
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Nama Regional <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $item->name) }}" required
               class="border rounded px-3 py-2 w-full @error('name') border-red-400 @enderror"
               placeholder="Jakarta Pusat">
        @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Provinsi</label>
        <input type="text" name="province" value="{{ old('province', $item->province) }}"
               class="border rounded px-3 py-2 w-full" placeholder="DKI Jakarta">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Entitas Legal</label>
        <select name="legal_entity_id" class="border rounded px-3 py-2 w-full">
          <option value="">-- Pilih Entitas --</option>
          @foreach($legalEntities as $le)
            <option value="{{ $le->id }}" @selected(old('legal_entity_id', $item->legal_entity_id) == $le->id)>
              {{ $le->short_name ?? $le->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">UMR (Rp)</label>
        <input type="number" name="umr_amount" value="{{ old('umr_amount', $item->umr_amount) }}"
               class="border rounded px-3 py-2 w-full" min="0" step="1000" placeholder="5000000">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Tahun UMR</label>
        <input type="number" name="umr_year" value="{{ old('umr_year', $item->umr_year ?? date('Y')) }}"
               class="border rounded px-3 py-2 w-full" min="2000" max="2100">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Kode Area BPJS</label>
        <input type="text" name="bpjs_area_code" value="{{ old('bpjs_area_code', $item->bpjs_area_code) }}"
               class="border rounded px-3 py-2 w-full" placeholder="0301">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <label class="flex items-center gap-2 cursor-pointer select-none mt-1">
          <div class="relative">
            <input type="checkbox" name="is_active" id="toggle_is_active" value="1"
                   class="sr-only peer"
                   {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
            <div class="w-11 h-6 bg-red-400 rounded-full peer peer-checked:bg-green-500
                        peer-focus:ring-2 peer-focus:ring-green-300 transition-colors duration-200"></div>
            <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow
                        peer-checked:translate-x-5 transition-transform duration-200"></div>
          </div>
          <span id="status_label"
                class="text-sm font-medium {{ old('is_active', $item->is_active ?? true) ? 'text-green-600' : 'text-red-500' }}">
            {{ old('is_active', $item->is_active ?? true) ? 'Aktif' : 'Non-aktif' }}
          </span>
        </label>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Catatan</label>
        <textarea name="notes" rows="2" class="border rounded px-3 py-2 w-full">{{ old('notes', $item->notes) }}</textarea>
      </div>
    </div>

    {{-- Outlet Linking (edit mode only) --}}
    @if($item->exists && $allOutlets->count() > 0)
    <details class="border rounded-lg overflow-hidden" {{ !empty($linkedIds) ? 'open' : '' }}>
      <summary class="px-4 py-2.5 bg-gray-50 cursor-pointer text-sm font-medium text-gray-600
                      hover:bg-gray-100 list-none flex items-center justify-between select-none">
        <span>Outlet Terhubung
          @if(!empty($linkedIds))
            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700">{{ count($linkedIds) }}</span>
          @endif
        </span>
        <span class="text-gray-400 text-xs">▾</span>
      </summary>
      <div class="px-4 py-3 border-t space-y-1 max-h-64 overflow-y-auto">
        @foreach($allOutlets as $o)
          @php
            $isLinkedHere  = $o->region_id === $item->id;
            $isLinkedOther = $o->region_id !== null && $o->region_id !== $item->id;
          @endphp
          <label class="flex items-center gap-3 py-1 px-2 rounded
                        {{ $isLinkedOther ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50' }}">
            <input type="checkbox" name="outlet_link_ids[]" value="{{ $o->id }}"
                   class="rounded border-gray-300"
                   {{ in_array($o->id, old('outlet_link_ids', $linkedIds)) ? 'checked' : '' }}
                   {{ $isLinkedOther ? 'disabled' : '' }}>
            <span class="text-sm">{{ $o->name }}</span>
            @if($isLinkedOther)
              <span class="text-xs text-amber-600 ml-auto">milik: {{ $o->region?->name ?? '—' }}</span>
            @endif
          </label>
        @endforeach
      </div>
    </details>
    @endif

    <div class="flex gap-3 pt-2">
      <button type="submit" class="px-5 py-2 rounded bg-gray-900 text-white text-sm">Simpan</button>
      <a href="{{ route('master.regions.index') }}" class="px-5 py-2 rounded border text-sm">Batal</a>
    </div>
  </form>
</div>

<script>
document.getElementById('toggle_is_active').addEventListener('change', function () {
    const label = document.getElementById('status_label');
    if (this.checked) {
        label.textContent = 'Aktif';
        label.className = 'text-sm font-medium text-green-600';
    } else {
        label.textContent = 'Non-aktif';
        label.className = 'text-sm font-medium text-red-500';
    }
});
</script>
@endsection
