@extends('layouts.app')
@section('content')
<div class="max-w-2xl mx-auto space-y-4">

  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

    {{-- Header + Toggle Status --}}
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h1 class="text-lg font-semibold text-gray-800">
        {{ $item->exists ? 'Edit Entitas Legal' : 'Tambah Entitas Legal' }}
      </h1>
      <label class="flex items-center gap-2 cursor-pointer select-none">
        <span class="text-sm text-gray-500">Status</span>
        <div class="relative">
          <input type="checkbox"
                 name="is_active"
                 id="toggle_is_active"
                 value="1"
                 class="sr-only peer"
                 form="main_form"
                 {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
          <div class="w-11 h-6 bg-red-400 rounded-full peer
                      peer-checked:bg-green-500
                      peer-focus:ring-2 peer-focus:ring-green-300
                      transition-colors duration-200"></div>
          <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow
                      peer-checked:translate-x-5 transition-transform duration-200"></div>
        </div>
        <span id="status_label"
              class="text-sm font-medium {{ old('is_active', $item->is_active ?? true) ? 'text-green-600' : 'text-red-500' }}">
          {{ old('is_active', $item->is_active ?? true) ? 'Aktif' : 'Non-aktif' }}
        </span>
      </label>
    </div>

    @if($errors->any())
      <div class="mx-6 mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
        <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form id="main_form"
          method="POST"
          action="{{ $item->exists ? route('master.legal-entities.update', $item) : route('master.legal-entities.store') }}"
          class="px-6 py-5 space-y-4">
      @csrf
      @if($item->exists) @method('PUT') @endif

      {{-- Nama + Singkat --}}
      <div class="grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Nama Entitas <span class="text-red-500">*</span></label>
          <input type="text" name="name" value="{{ old('name', $item->name) }}" required
                 class="border rounded px-3 py-2 w-full @error('name') border-red-400 @enderror"
                 placeholder="PT Omeo Nusantara">
          @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Nama Singkat</label>
          <input type="text" name="short_name" value="{{ old('short_name', $item->short_name) }}"
                 class="border rounded px-3 py-2 w-full @error('short_name') border-red-400 @enderror"
                 placeholder="OMEO" maxlength="50">
          @error('short_name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tipe Entitas <span class="text-red-500">*</span></label>
          <select name="entity_type" class="border rounded px-3 py-2 w-full" required>
            @foreach(['PT', 'CV', 'UD', 'Firma', 'Yayasan', 'Lainnya'] as $type)
              <option value="{{ $type }}" @selected(old('entity_type', $item->entity_type) === $type)>{{ $type }}</option>
            @endforeach
          </select>
        </div>
      </div>

      {{-- Group Perusahaan --}}
      <div>
        <label class="block text-sm font-medium mb-1">Group Perusahaan</label>
        <select name="company_group_id" class="border rounded px-3 py-2 w-full">
          <option value="">-- Tidak ada --</option>
          @foreach($companyGroups as $cg)
            <option value="{{ $cg->id }}" @selected(old('company_group_id', $item->company_group_id) == $cg->id)>{{ $cg->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- NPWP + NIB --}}
      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="block text-sm font-medium mb-1">NPWP</label>
          <input type="text" name="npwp" value="{{ old('npwp', $item->npwp) }}"
                 class="border rounded px-3 py-2 w-full" placeholder="xx.xxx.xxx.x-xxx.xxx">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">NIB</label>
          <input type="text" name="nib" value="{{ old('nib', $item->nib) }}"
                 class="border rounded px-3 py-2 w-full">
        </div>
      </div>

      {{-- Alamat --}}
      <div>
        <label class="block text-sm font-medium mb-1">Alamat</label>
        <textarea name="address" rows="2" class="border rounded px-3 py-2 w-full">{{ old('address', $item->address) }}</textarea>
      </div>

      {{-- Kota dengan datalist --}}
      <div>
        <label class="block text-sm font-medium mb-1">Kota</label>
        <input type="text" name="city" value="{{ old('city', $item->city) }}"
               class="border rounded px-3 py-2 w-full"
               list="city_list" placeholder="Contoh: Jakarta" autocomplete="off">
        <datalist id="city_list">
          @foreach(['Jakarta','Surabaya','Bandung','Bekasi','Medan','Tangerang','Depok','Semarang',
                     'Palembang','Makassar','Tangerang Selatan','Batam','Pekanbaru','Bogor',
                     'Bandar Lampung','Malang','Padang','Denpasar','Samarinda','Pontianak',
                     'Manado','Balikpapan','Banjarmasin','Mataram','Serang','Sukabumi',
                     'Jambi','Cimahi','Probolinggo','Solo','Yogyakarta','Ambon',
                     'Sorong','Jayapura','Ternate','Kendari','Palu','Kupang',
                     'Gorontalo','Palangka Raya'] as $city)
            <option value="{{ $city }}">
          @endforeach
        </datalist>
      </div>

      {{-- Collapsible: Kontak & Direktur --}}
      <details class="border rounded-lg overflow-hidden" {{ $item->phone || $item->email || $item->director_name ? 'open' : '' }}>
        <summary class="px-4 py-2.5 bg-gray-50 cursor-pointer text-sm font-medium text-gray-600
                        hover:bg-gray-100 list-none flex items-center justify-between select-none">
          <span>Kontak &amp; Direktur <span class="text-xs font-normal text-gray-400">(opsional)</span></span>
          <span class="text-gray-400 text-xs">▾</span>
        </summary>
        <div class="px-4 py-4 space-y-4 border-t">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="block text-sm font-medium mb-1">Telepon</label>
              <input type="text" name="phone" value="{{ old('phone', $item->phone) }}"
                     class="border rounded px-3 py-2 w-full">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Email</label>
              <input type="email" name="email" value="{{ old('email', $item->email) }}"
                     class="border rounded px-3 py-2 w-full">
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Nama Direktur</label>
              <input type="text" name="director_name" value="{{ old('director_name', $item->director_name) }}"
                     class="border rounded px-3 py-2 w-full">
            </div>
          </div>
        </div>
      </details>

      {{-- Catatan --}}
      <div>
        <label class="block text-sm font-medium mb-1">Catatan</label>
        <textarea name="notes" rows="2" class="border rounded px-3 py-2 w-full">{{ old('notes', $item->notes) }}</textarea>
      </div>

      {{-- Outlet Linking (edit mode only) --}}
      @if($item->exists && $outlets->count() > 0)
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
          @foreach($outlets as $o)
            <label class="flex items-center gap-3 py-1 px-2 cursor-pointer hover:bg-gray-50 rounded">
              <input type="checkbox" name="outlet_link_ids[]" value="{{ $o->id }}"
                     class="rounded border-gray-300"
                     {{ in_array($o->id, old('outlet_link_ids', $linkedIds)) ? 'checked' : '' }}>
              <span class="text-sm">{{ $o->name }}</span>
              @if($o->legal_entity_id && $o->legal_entity_id !== $item->id)
                <span class="text-xs text-amber-600 ml-auto">(terhubung ke entitas lain)</span>
              @endif
            </label>
          @endforeach
        </div>
      </details>
      @endif

      <div class="flex gap-3 pt-2 border-t border-gray-100">
        <button type="submit" class="px-5 py-2 rounded bg-gray-900 text-white text-sm">Simpan</button>
        <a href="{{ route('master.legal-entities.index') }}" class="px-5 py-2 rounded border text-sm">Batal</a>
      </div>
    </form>
  </div>
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
