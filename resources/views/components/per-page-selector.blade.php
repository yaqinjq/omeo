@props(['perPage' => 20])

<div class="flex items-center gap-2">
    <label class="text-sm text-gray-500 whitespace-nowrap">
        Tampilkan:
    </label>
    <select onchange="window.location.href=this.value"
            class="text-sm border border-gray-300 rounded-lg px-2 py-1.5
                   bg-white text-gray-700 cursor-pointer
                   focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
        @foreach([20, 50, 100, 200] as $opt)
        <option value="{{ request()->fullUrlWithQuery(['per_page' => $opt, 'page' => 1]) }}"
                {{ (int) $perPage === $opt ? 'selected' : '' }}>
            {{ $opt }} per halaman
        </option>
        @endforeach
        <option value="{{ request()->fullUrlWithQuery(['per_page' => 9999, 'page' => 1]) }}"
                {{ (int) $perPage === 9999 ? 'selected' : '' }}>
            Semua
        </option>
    </select>
</div>
