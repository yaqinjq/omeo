@props([
    'title',
    'name',
    'items',
    'fields',
    'required' => false,
    'requiredNotice' => 'Wajib diisi minimal 1 baris.',
    'minRows' => 0,
])

@php
    $items = is_array($items) ? array_values($items) : [];
    $minRows = max((int) $minRows, $required ? 1 : 0);

    while (count($items) < $minRows) {
        $items[] = [];
    }

    $sectionErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => $key === $name || str_starts_with($key, $name . '.'))
        ->flatten()
        ->unique()
        ->values();
@endphp

<section
    id="repeatable-{{ $name }}"
    data-repeatable-name="{{ $name }}"
    data-repeatable-required="{{ $required ? '1' : '0' }}"
    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm mb-6"
>
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-700">{{ $title }}</h3>
            @if($required)
                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-700">wajib</span>
            @endif
        </div>

        <button type="button"
                onclick="addRepeatableRow('table_{{ $name }}', '{{ base64_encode(json_encode($fields)) }}', '{{ $name }}')"
                class="inline-flex items-center rounded-xl border border-blue-600 bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700">
            <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Data
        </button>
    </div>

    @if($sectionErrors->isNotEmpty())
        <div class="border-b border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 space-y-1" data-repeatable-error-summary="{{ $name }}">
            <div class="font-semibold text-red-800">Masih ada data yang perlu diperbaiki pada bagian {{ strtolower($title) }}.</div>
            <ul class="list-disc pl-5 space-y-1">
                @foreach($sectionErrors as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm text-left" id="table_{{ $name }}">
            <thead class="border-b bg-slate-100 text-slate-600">
                <tr>
                    @foreach($fields as $field)
                        <th class="px-4 py-3 font-semibold min-w-[150px]">{{ $field['label'] }}</th>
                    @endforeach
                    <th class="px-4 py-3 w-[50px] text-center font-semibold">#</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($items as $index => $item)
                    <tr class="hover:bg-slate-50 transition" data-repeatable-row="{{ $name }}-{{ $index }}">
                        @foreach($fields as $field)
                            @php
                                $type = $field['type'] ?? 'text';
                                $inputName = $name . '[' . $index . '][' . $field['key'] . ']';
                                $errorKey = $name . '.' . $index . '.' . $field['key'];
                                $value = $item[$field['key']] ?? '';
                            @endphp
                            <td class="p-2 align-top" data-error-container="{{ $errorKey }}">
                                @if($type === 'select')
                                    <select class="block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                            name="{{ $inputName }}"
                                            data-error-key="{{ $errorKey }}"
                                            {{ $required ? 'required' : '' }}>
                                        <option value="">Pilih...</option>
                                        @foreach($field['options'] as $opt)
                                            <option value="{{ $opt }}" {{ $value == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @elseif($type === 'date')
                                    <input type="date"
                                           class="block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                           name="{{ $inputName }}"
                                           data-error-key="{{ $errorKey }}"
                                           value="{{ $value }}"
                                           {{ $required ? 'required' : '' }}>
                                @elseif($type === 'number')
                                    <input type="number"
                                           class="block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                           name="{{ $inputName }}"
                                           data-error-key="{{ $errorKey }}"
                                           value="{{ $value }}"
                                           placeholder="{{ $field['placeholder'] ?? '' }}"
                                           {{ $required ? 'required' : '' }}>
                                @elseif($type === 'textarea')
                                    <textarea class="block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                              rows="2"
                                              name="{{ $inputName }}"
                                              data-error-key="{{ $errorKey }}"
                                              placeholder="{{ $field['placeholder'] ?? '' }}"
                                              {{ $required ? 'required' : '' }}>{{ $value }}</textarea>
                                @else
                                    <input type="text"
                                           class="block w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                           name="{{ $inputName }}"
                                           data-error-key="{{ $errorKey }}"
                                           value="{{ $value }}"
                                           placeholder="{{ $field['placeholder'] ?? '' }}"
                                           {{ $required ? 'required' : '' }}>
                                @endif
                            </td>
                        @endforeach
                        <td class="p-2 text-center align-top">
                            <button type="button" class="text-red-500 hover:text-red-700 transition" onclick="removeRow(this)">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>

        @if($required)
            <div class="border-t border-red-100 bg-red-50 px-4 py-3 text-xs font-semibold text-red-600">
                {{ $requiredNotice }}
            </div>
        @elseif(empty($items))
            <div class="bg-white px-6 py-5 text-center text-sm italic text-slate-400">
                Belum ada data {{ strtolower($title) }}. Silakan klik <b>Tambah Data</b>.
            </div>
        @endif
    </div>
</section>
