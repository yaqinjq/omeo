@extends('layouts.app')
@section('title', $mode === 'create' ? 'Tambah Mapping Departemen' : 'Edit Mapping Departemen')
@section('content')
<div class="max-w-xl mx-auto space-y-4">

  <div class="flex items-center gap-2 text-sm text-gray-500">
    <a href="{{ route('finance.department-mappings.index') }}" class="hover:text-indigo-600">Mapping Departemen</a>
    <span>/</span>
    <span class="text-gray-700 font-medium">{{ $mode === 'create' ? 'Tambah Baru' : 'Edit' }}</span>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h1 class="text-lg font-semibold text-gray-800">
        {{ $mode === 'create' ? 'Tambah Mapping Departemen' : 'Edit Mapping' }}
      </h1>
      <label class="flex items-center gap-2 cursor-pointer select-none">
        <span class="text-sm text-gray-500">Status</span>
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

    <form action="{{ $mode === 'create'
        ? route('finance.department-mappings.store')
        : route('finance.department-mappings.update', $item) }}"
          method="POST" class="px-6 py-5 space-y-4">
      @csrf
      @if($mode === 'edit') @method('PUT') @endif

      @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
          <ul class="space-y-1">@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
        </div>
      @endif

      <div>
        <label class="block text-sm font-medium mb-1">Tipe Match <span class="text-red-500">*</span></label>
        <select name="match_type" class="border rounded px-3 py-2 w-full text-sm" required>
          <option value="sub_dept" @selected(old('match_type', $item->match_type) === 'sub_dept')>Sub Dept (Level 1 — default)</option>
          <option value="position" @selected(old('match_type', $item->match_type) === 'position')>Posisi / Jabatan (Level 2 — override)</option>
        </select>
        <p class="text-xs text-gray-400 mt-1">Posisi menang atas Sub Dept jika keduanya cocok.</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Nilai (case-insensitive) <span class="text-red-500">*</span></label>
        <input type="text" name="match_value" value="{{ old('match_value', $item->match_value) }}"
               class="border rounded px-3 py-2 w-full text-sm font-mono uppercase @error('match_value') border-red-400 @enderror"
               placeholder="Contoh: KITCHEN OUTLET" required>
        @error('match_value')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Kategori <span class="text-red-500">*</span></label>
        @php
          $cats = ['bar'=>'Bar','kitchen'=>'Kitchen','service'=>'Service','office'=>'Office','prive'=>'Prive (Direktur)','lainnya'=>'Lainnya'];
        @endphp
        <select name="category" class="border rounded px-3 py-2 w-full text-sm" required>
          @foreach($cats as $val => $label)
            <option value="{{ $val }}" @selected(old('category', $item->category) === $val)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Catatan</label>
        <textarea name="notes" rows="2" class="border rounded px-3 py-2 w-full text-sm">{{ old('notes', $item->notes) }}</textarea>
      </div>

      <div class="flex gap-3 pt-2 border-t border-gray-100">
        <button type="submit" class="px-5 py-2 rounded bg-gray-900 text-white text-sm">
          {{ $mode === 'create' ? 'Simpan' : 'Perbarui' }}
        </button>
        <a href="{{ route('finance.department-mappings.index') }}" class="px-5 py-2 rounded border text-sm">Batal</a>
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
