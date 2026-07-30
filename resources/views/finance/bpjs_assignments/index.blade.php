@extends('layouts.app')
@section('title', 'BPJS Assignment')
@section('content')

<div class="max-w-7xl mx-auto px-4 py-6 space-y-4"
     x-data="{
         showAdd: false,
         showImport: false,
         editId: null,
         empSearch: '',
         empResults: [],
         empSelected: null,
         editData: {},
         searchTimeout: null,
         searchEmployees() {
             clearTimeout(this.searchTimeout);
             if (this.empSearch.length < 2) { this.empResults = []; return; }
             this.searchTimeout = setTimeout(() => {
                 fetch('{{ route('finance.bpjs-assignments.search-employee') }}?q=' + encodeURIComponent(this.empSearch))
                     .then(r => r.json()).then(d => { this.empResults = d; });
             }, 300);
         },
         selectEmp(emp) {
             this.empSelected = emp;
             this.empSearch   = emp.full_name + ' (' + (emp.employee_number || emp.nokom || '') + ')';
             this.empResults  = [];
         },
         openEdit(data) {
             this.editId   = data.id;
             this.editData = { ...data };
             this.editData._visible = true;
         },
         closeEdit() { this.editId = null; this.editData = {}; }
     }">

    {{-- ── Header ── --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">BPJS Assignment</h1>
            <p class="text-sm text-gray-500 mt-0.5">Master penugasan PT yang menanggung BPJS per karyawan.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button @click="showAdd = true"
                    style="background-color:#059669;color:#fff;"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Assignment
            </button>
            <button @click="showImport = true"
                    style="background-color:#7C3AED;color:#fff;"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import Excel
            </button>
            <a href="{{ route('finance.bpjs-assignments.template') }}"
               style="background-color:#4B5563;color:#fff;text-decoration:none;"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Template Import
            </a>
            <a href="{{ route('finance.bpjs-assignments.cross-billing') }}"
               style="background-color:#1D4ED8;color:#fff;text-decoration:none;"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Cross-billing
            </a>
        </div>
    </div>

    {{-- ── Flash ── --}}
    @if(session('success'))
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
    @if(session('import_errors'))
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 space-y-1">
            <p class="font-medium">Baris yang gagal diimport:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Filter ── --}}
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Cari nama / NIK / no. karyawan..."
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400 w-64">
        <select name="legal_entity_id"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
            <option value="">Semua PT</option>
            @foreach($legalEntities as $pt)
                <option value="{{ $pt->id }}" {{ request('legal_entity_id') == $pt->id ? 'selected' : '' }}>
                    {{ $pt->short_name ?? $pt->name }}
                </option>
            @endforeach
        </select>
        <select name="status"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
            <option value="">Semua Status</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
            <option value="ended" {{ request('status') === 'ended' ? 'selected' : '' }}>Berakhir</option>
        </select>
        <button type="submit"
                class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Filter</button>
        @if(request()->hasAny(['search','legal_entity_id','status']))
            <a href="{{ route('finance.bpjs-assignments.index') }}"
               class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700">Reset</a>
        @endif
    </form>

    {{-- ── Tabel ── --}}
    <div class="bg-white rounded-lg border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left w-8">#</th>
                    <th class="px-4 py-3 text-left">Karyawan</th>
                    <th class="px-4 py-3 text-left">PT BPJS</th>
                    <th class="px-4 py-3 text-left">Mulai Berlaku</th>
                    <th class="px-4 py-3 text-left">Berakhir</th>
                    <th class="px-4 py-3 text-left">Alasan</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($assignments as $i => $a)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-gray-400 text-xs">
                            {{ $assignments->firstItem() + $i }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $a->employee?->full_name ?? '-' }}</div>
                            <div class="text-xs text-gray-400 font-mono">
                                NIK: {{ $a->employee?->nik ?? '-' }}
                                @if($a->employee?->employee_number)
                                    · No. {{ $a->employee->employee_number }}
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $a->legalEntity?->short_name ?? $a->legalEntity?->name ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $a->effective_date?->format('d/m/Y') ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $a->end_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs text-gray-600">{{ $a->reason_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($a->is_active)
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                                      style="background-color:#D1FAE5;color:#065F46;">Aktif</span>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                                      style="background-color:#F3F4F6;color:#6B7280;">Berakhir</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button @click="openEdit({
                                        id: {{ $a->id }},
                                        legal_entity_id: {{ $a->legal_entity_id }},
                                        effective_date: '{{ $a->effective_date?->format('Y-m-d') }}',
                                        end_date: '{{ $a->end_date?->format('Y-m-d') ?? '' }}',
                                        reason: '{{ $a->reason }}',
                                        notes: '{{ addslashes($a->notes ?? '') }}'
                                    })"
                                    class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-3">
                                Edit
                            </button>
                            <form method="POST"
                                  action="{{ route('finance.bpjs-assignments.destroy', $a->id) }}"
                                  class="inline"
                                  onsubmit="return confirm('Hapus assignment BPJS ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">
                                    Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">
                            Belum ada data assignment BPJS.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $assignments->links() }}

    {{-- ════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Tambah Assignment --}}
    {{-- ════════════════════════════════════════════════════════ --}}
    <div x-show="showAdd"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.5);"
         @click.self="showAdd = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4"
             @click.stop>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Tambah Assignment BPJS</h2>
                <button @click="showAdd = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('finance.bpjs-assignments.store') }}" class="space-y-3">
                @csrf

                {{-- Live search karyawan --}}
                <div class="relative">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Karyawan <span class="text-red-500">*</span></label>
                    <input type="text"
                           x-model="empSearch"
                           @input="searchEmployees()"
                           placeholder="Ketik nama / NIK / no. karyawan..."
                           autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                    <input type="hidden" name="employee_id"
                           :value="empSelected ? empSelected.id : ''">
                    {{-- Dropdown hasil --}}
                    <div x-show="empResults.length > 0"
                         class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200
                                rounded-lg shadow-lg z-10 max-h-48 overflow-y-auto">
                        <template x-for="emp in empResults" :key="emp.id">
                            <button type="button"
                                    @click="selectEmp(emp)"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 border-b last:border-b-0">
                                <span class="font-medium" x-text="emp.full_name"></span>
                                <span class="text-gray-400 text-xs ml-1"
                                      x-text="'(' + (emp.employee_number || emp.nokom || emp.nik || '') + ')'"></span>
                            </button>
                        </template>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"
                       x-show="empSelected"
                       x-text="'ID terpilih: ' + (empSelected ? empSelected.id : '')"></p>
                </div>

                {{-- PT BPJS --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PT BPJS <span class="text-red-500">*</span></label>
                    <select name="legal_entity_id" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="">— Pilih PT —</option>
                        @foreach($legalEntities as $pt)
                            <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tanggal --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mulai Berlaku <span class="text-red-500">*</span></label>
                        <input type="date" name="effective_date" required
                               value="{{ now()->format('Y-m-d') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Berakhir <span class="text-gray-400">(opsional)</span></label>
                        <input type="date" name="end_date"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                </div>

                {{-- Alasan --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alasan <span class="text-red-500">*</span></label>
                    <select name="reason" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="join">Bergabung (join)</option>
                        <option value="transfer">Pindah PT (transfer)</option>
                        <option value="resign">Resign</option>
                    </select>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Catatan</label>
                    <input type="text" name="notes" maxlength="255"
                           placeholder="Catatan tambahan (opsional)"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showAdd = false"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit"
                            style="background-color:#059669;color:#fff;"
                            class="px-4 py-2 text-sm font-medium rounded-lg">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Edit Assignment --}}
    {{-- ════════════════════════════════════════════════════════ --}}
    <div x-show="editId !== null"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.5);"
         @click.self="closeEdit()">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4"
             @click.stop>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Edit Assignment BPJS</h2>
                <button @click="closeEdit()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST"
                  :action="'{{ url('finance/bpjs-assignments') }}/' + editId"
                  class="space-y-3">
                @csrf @method('PUT')

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PT BPJS <span class="text-red-500">*</span></label>
                    <select name="legal_entity_id" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="">— Pilih PT —</option>
                        @foreach($legalEntities as $pt)
                            <option value="{{ $pt->id }}"
                                    :selected="editData.legal_entity_id == {{ $pt->id }}">
                                {{ $pt->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mulai Berlaku <span class="text-red-500">*</span></label>
                        <input type="date" name="effective_date" required
                               :value="editData.effective_date"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Berakhir <span class="text-gray-400">(opsional)</span></label>
                        <input type="date" name="end_date"
                               :value="editData.end_date"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alasan <span class="text-red-500">*</span></label>
                    <select name="reason" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="join"     :selected="editData.reason === 'join'">Bergabung (join)</option>
                        <option value="transfer" :selected="editData.reason === 'transfer'">Pindah PT (transfer)</option>
                        <option value="resign"   :selected="editData.reason === 'resign'">Resign</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Catatan</label>
                    <input type="text" name="notes" maxlength="255"
                           :value="editData.notes"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-400">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="closeEdit()"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit"
                            style="background-color:#1D4ED8;color:#fff;"
                            class="px-4 py-2 text-sm font-medium rounded-lg">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Import Excel --}}
    {{-- ════════════════════════════════════════════════════════ --}}
    <div x-show="showImport"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.5);"
         @click.self="showImport = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4"
             @click.stop>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Import Excel</h2>
                <button @click="showImport = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800">
                <p class="font-medium mb-1">Petunjuk:</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    <li>Download template terlebih dahulu</li>
                    <li>Kolom <code>reason</code>: isi dengan <code>join</code>, <code>transfer</code>, atau <code>resign</code></li>
                    <li>Nama PT harus sesuai persis dengan sheet "Daftar PT" di template</li>
                    <li>Kolom <code>end_date</code> boleh dikosongkan</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('finance.bpjs-assignments.import') }}"
                  enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">File Excel (.xlsx) <span class="text-red-500">*</span></label>
                    <input type="file" name="file" accept=".xlsx,.xls" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-purple-400">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showImport = false"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit"
                            style="background-color:#7C3AED;color:#fff;"
                            class="px-4 py-2 text-sm font-medium rounded-lg">
                        Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
