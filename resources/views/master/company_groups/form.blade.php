@extends('layouts.app')
@section('title', $mode === 'create' ? 'Tambah Group Perusahaan' : 'Edit Group Perusahaan')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
        <a href="{{ route('master.company-groups.index') }}"
           class="hover:text-indigo-600 transition-colors">Group Perusahaan</a>
        <span>/</span>
        <span class="text-gray-700 font-medium">
            {{ $mode === 'create' ? 'Tambah Baru' : 'Edit: ' . $group->name }}
        </span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        {{-- Header card --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">
                {{ $mode === 'create' ? 'Tambah Group Perusahaan' : 'Edit Group Perusahaan' }}
            </h1>
            {{-- Toggle Status --}}
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <span class="text-sm text-gray-500">Status</span>
                <div class="relative">
                    <input type="checkbox"
                           name="is_active"
                           id="toggle_is_active"
                           value="1"
                           class="sr-only peer"
                           {{ old('is_active', $group->is_active ?? true) ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-red-400 rounded-full peer
                                peer-checked:bg-green-500
                                peer-focus:ring-2 peer-focus:ring-green-300
                                transition-colors duration-200"></div>
                    <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow
                                peer-checked:translate-x-5 transition-transform duration-200"></div>
                </div>
                <span id="status_label"
                      class="text-sm font-medium {{ old('is_active', $group->is_active ?? true) ? 'text-green-600' : 'text-red-500' }}">
                    {{ old('is_active', $group->is_active ?? true) ? 'Aktif' : 'Non-aktif' }}
                </span>
            </label>
        </div>

        {{-- Form --}}
        <form action="{{ $mode === 'create' ? route('master.company-groups.store') : route('master.company-groups.update', $group) }}"
              method="POST"
              class="px-6 py-5 space-y-5">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif

            @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                <ul class="space-y-1">
                    @foreach($errors->all() as $e)
                        <li>• {{ $e }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Nama Group --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nama Group Perusahaan <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="name"
                       value="{{ old('name', $group->name ?? '') }}"
                       placeholder="Contoh: PT Makmur Ramenjaya Mandiri"
                       class="w-full border rounded-lg px-3 py-2 text-sm
                              focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400
                              {{ $errors->has('name') ? 'border-red-400' : 'border-gray-300' }}">
                @error('name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Nama Singkat + NPWP --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Singkat / Inisial
                    </label>
                    <input type="text"
                           name="short_name"
                           value="{{ old('short_name', $group->short_name ?? '') }}"
                           placeholder="Contoh: MKO"
                           maxlength="50"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        NPWP Holding
                        <span class="text-gray-400 font-normal text-xs">(opsional)</span>
                    </label>
                    <input type="text"
                           name="npwp"
                           value="{{ old('npwp', $group->npwp ?? '') }}"
                           placeholder="XX.XXX.XXX.X-XXX.XXX"
                           maxlength="30"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                </div>
            </div>

            {{-- Alamat --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Alamat Kantor Pusat
                    <span class="text-gray-400 font-normal text-xs">(opsional)</span>
                </label>
                <textarea name="address"
                          rows="2"
                          placeholder="Jl. ..."
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                 focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400
                                 resize-none">{{ old('address', $group->address ?? '') }}</textarea>
            </div>

            {{-- Kota + Website --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Kota Pusat
                        <span class="text-gray-400 font-normal text-xs">(opsional)</span>
                    </label>
                    <input type="text"
                           name="headquarters_city"
                           value="{{ old('headquarters_city', $group->headquarters_city ?? '') }}"
                           placeholder="Contoh: Malang"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Website
                        <span class="text-gray-400 font-normal text-xs">(opsional)</span>
                    </label>
                    <input type="text"
                           name="website"
                           value="{{ old('website', $group->website ?? '') }}"
                           placeholder="https://..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                </div>
            </div>

            {{-- Tombol --}}
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                <a href="{{ route('master.company-groups.index') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100
                          rounded-lg hover:bg-gray-200 transition-colors">
                    Batal
                </a>
                <button type="submit"
                        class="px-6 py-2 text-sm font-medium text-white bg-indigo-600
                               rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $mode === 'create' ? 'Simpan' : 'Perbarui' }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('toggle_is_active').addEventListener('change', function() {
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
