@php
    $normalizedRows = is_array($rows) && count($rows) > 0 ? $rows : [[]];
    $encodedColumns = base64_encode(json_encode($columns));
@endphp
<div class="space-y-3">
    <div class="flex items-center justify-between gap-3">
        <h3 class="font-semibold text-slate-900">{{ $title }}</h3>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" onclick="addRepeatableRow('{{ $name }}', '{{ $encodedColumns }}')">+ Tambah</button>
    </div>
    <div class="space-y-3" data-repeatable-body="{{ $name }}">
        @foreach($normalizedRows as $index => $row)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" data-repeatable-row>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-slate-900">Baris {{ $loop->iteration }}</div>
                    <button type="button" class="text-sm font-medium text-red-600" onclick="this.closest('[data-repeatable-row]').remove()">Hapus</button>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($columns as $column)
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-slate-700">{{ $column['label'] }}</span>
                            <input
                                type="{{ $column['type'] ?? 'text' }}"
                                name="{{ $name }}[{{ $index }}][{{ $column['key'] }}]"
                                value="{{ old($name . '.' . $index . '.' . $column['key'], $row[$column['key']] ?? '') }}"
                                class="w-full rounded-lg border px-3 py-2 text-sm"
                            >
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
