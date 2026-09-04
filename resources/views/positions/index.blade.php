@extends('layouts.app')
@section('title', 'Master Posisi & Departemen')
@section('content')
<div class="p-6 space-y-5" x-data="positionsPage()" x-cloak>

    {{-- HEADER --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Master Posisi & Departemen</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola hierarki departemen dan posisi karyawan</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('positions.org-chart') }}"
               class="px-4 py-2 text-sm rounded-lg font-medium flex items-center gap-1.5"
               style="background:#7c3aed;color:#fff">
                🌳 Lihat Org Chart
            </a>
            <button type="button" @click="deptModal.open()"
                    class="px-4 py-2 text-sm rounded-lg font-medium"
                    style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe">
                + Tambah Departemen
            </button>
            <button type="button" @click="posModal.open()"
                    class="px-4 py-2 text-sm rounded-lg font-medium"
                    style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0">
                + Tambah Posisi
            </button>
            <div class="relative" x-data="{openImport:false}">
                <button type="button" @click="openImport=!openImport"
                        class="px-4 py-2 text-sm rounded-lg font-medium flex items-center gap-1.5"
                        style="background:#7c3aed;color:#fff">
                    ⬇ Import / Template
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </button>
                <div x-show="openImport" @click.outside="openImport=false" x-transition
                     class="absolute right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 z-30"
                     style="top:100%;min-width:11rem">
                    <a href="{{ route('positions.template') }}"
                       class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 rounded-t-lg">
                        📄 Download Template
                    </a>
                    <button type="button" @click="openImport=false; importModal.show=true"
                            class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 rounded-b-lg text-left">
                        ⬆ Upload Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- FLASH MESSAGES --}}
    @if(session('success'))
    <div class="rounded-lg px-4 py-3 text-sm" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="rounded-lg px-4 py-3 text-sm" style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b">
        ✗ {{ session('error') }}
    </div>
    @endif
    @if(session('import_warnings'))
    <div class="rounded-lg p-4 text-sm space-y-1" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e">
        <div class="font-semibold mb-1">⚠ Warning Import:</div>
        @foreach(session('import_warnings') as $warn)
            <div class="text-xs">{{ $warn }}</div>
        @endforeach
    </div>
    @endif

    {{-- SUMMARY CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-3"
             style="border:1px solid #e2e8f0">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 text-xl"
                 style="background:#dbeafe">🏢</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $departments->count() }}</p>
                <p class="text-xs text-gray-500">Departemen</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-3"
             style="border:1px solid #e2e8f0">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 text-xl"
                 style="background:#dcfce7">💼</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $positions->count() }}</p>
                <p class="text-xs text-gray-500">Total Posisi</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-3"
             style="border:1px solid #e2e8f0">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 text-xl"
                 style="background:#f3e8ff">👥</div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $totalEmployeesMapped }}</p>
                <p class="text-xs text-gray-500">Karyawan Ter-mapping</p>
            </div>
        </div>
    </div>

    {{-- MAIN CARD: TABS + TABLE --}}
    <div class="bg-white rounded-xl shadow-sm overflow-hidden" style="border:1px solid #e2e8f0">

        {{-- Tab Nav --}}
        <div class="border-b border-gray-200 overflow-x-auto">
            <div class="flex min-w-max">
                <button type="button"
                        @click="activeTab='all'"
                        :class="activeTab==='all' ? 'font-semibold border-b-2' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab==='all' ? 'border-color:#7c3aed;color:#7c3aed' : ''"
                        class="px-4 py-3 text-sm whitespace-nowrap transition-colors">
                    Semua Posisi
                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full"
                          style="background:#f3e8ff;color:#7c3aed">{{ $positions->count() }}</span>
                </button>

                @foreach($departments as $dept)
                <button type="button"
                        @click="activeTab='dept-{{ $dept->id }}'"
                        :class="activeTab==='dept-{{ $dept->id }}' ? 'font-semibold border-b-2' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab==='dept-{{ $dept->id }}' ? 'border-color:#7c3aed;color:#7c3aed' : ''"
                        class="px-4 py-3 text-sm whitespace-nowrap transition-colors">
                    {{ $dept->name }}
                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full"
                          style="background:#f1f5f9;color:#64748b">{{ $dept->positions_count }}</span>
                </button>
                @endforeach

                @if($groupedPositions->has(''))
                <button type="button"
                        @click="activeTab='no-dept'"
                        :class="activeTab==='no-dept' ? 'font-semibold border-b-2' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab==='no-dept' ? 'border-color:#f59e0b;color:#d97706' : ''"
                        class="px-4 py-3 text-sm whitespace-nowrap transition-colors">
                    Belum Terdepartemen
                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full"
                          style="background:#fef3c7;color:#92400e">
                        {{ $groupedPositions->get('')->count() }}
                    </span>
                </button>
                @endif
            </div>
        </div>

        {{-- Tab: Semua --}}
        <div x-show="activeTab==='all'" x-transition>
            @include('positions._position_table', ['rows' => $positions, 'showDept' => true])
        </div>

        {{-- Tab: Per Departemen --}}
        @foreach($departments as $dept)
        <div x-show="activeTab==='dept-{{ $dept->id }}'" x-transition>
            {{-- Dept info bar --}}
            <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-3"
                 style="background:#fafafa;border-bottom:1px solid #f1f5f9">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-800">{{ $dept->name }}</span>
                    @if($dept->code)
                    <span class="text-xs px-2 py-0.5 rounded font-mono"
                          style="background:#e0e7ff;color:#3730a3">{{ $dept->code }}</span>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button type="button"
                            @click="deptModal.open({{ Js::from([
                                'id'         => $dept->id,
                                'name'       => $dept->name,
                                'code'       => $dept->code ?? '',
                                'sort_order' => $dept->sort_order ?? 0,
                            ]) }})"
                            class="px-3 py-1.5 text-xs rounded-lg"
                            style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe">
                        Edit Dept
                    </button>
                    <form method="POST"
                          action="{{ route('positions.departments.destroy', $dept) }}"
                          onsubmit="return confirm('Hapus departemen {{ addslashes($dept->name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-3 py-1.5 text-xs rounded-lg"
                                style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca">
                            Hapus Dept
                        </button>
                    </form>
                </div>
            </div>
            @php $deptPositions = $groupedPositions->get($dept->id) ?? collect(); @endphp
            @include('positions._position_table', ['rows' => $deptPositions, 'showDept' => false])
        </div>
        @endforeach

        {{-- Tab: Belum Terdepartemen --}}
        @if($groupedPositions->has(''))
        <div x-show="activeTab==='no-dept'" x-transition>
            <div class="px-5 py-3 text-sm text-amber-700" style="background:#fffbeb;border-bottom:1px solid #fef3c7">
                ⚠ Posisi berikut belum memiliki departemen — assign via tombol Edit di kolom Aksi.
            </div>
            @include('positions._position_table', ['rows' => $groupedPositions->get(''), 'showDept' => false])
        </div>
        @endif
    </div>

    {{-- ================================================================ --}}
    {{-- MODAL: DEPARTEMEN                                                 --}}
    {{-- ================================================================ --}}
    <div x-show="deptModal.show" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.45)">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6"
             x-transition
             @click.outside="deptModal.close()">

            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-800"
                    x-text="deptModal.id ? 'Edit Departemen' : 'Tambah Departemen'"></h3>
                <button type="button" @click="deptModal.close()"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form :action="deptModal.id
                    ? '{{ url('positions/departments') }}/' + deptModal.id
                    : '{{ route('positions.departments.store') }}'"
                  method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="deptModal.id ? 'PUT' : 'POST'">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Departemen <span style="color:#dc2626">*</span>
                    </label>
                    <input type="text" name="name" x-model="deptModal.name"
                           required maxlength="150"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-purple-400"
                           placeholder="cth: HUMAN RESOURCE DEPARTMENT">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kode</label>
                        <input type="text" name="code" x-model="deptModal.code"
                               maxlength="30"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-purple-400"
                               placeholder="cth: HRD">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Urutan</label>
                        <input type="number" name="sort_order" x-model="deptModal.sort_order"
                               min="0"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-purple-400">
                    </div>
                </div>

                <div class="flex gap-2 justify-end pt-1">
                    <button type="button" @click="deptModal.close()"
                            class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm rounded-lg text-white font-medium"
                            style="background:#7c3aed">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- MODAL: POSISI                                                     --}}
    {{-- ================================================================ --}}
    <div x-show="posModal.show" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.45)">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 overflow-y-auto"
             style="max-height:90vh"
             x-transition
             @click.outside="posModal.close()">

            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-800"
                    x-text="posModal.id ? 'Edit Posisi' : 'Tambah Posisi'"></h3>
                <button type="button" @click="posModal.close()"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form :action="posModal.id
                    ? '{{ url('positions') }}/' + posModal.id
                    : '{{ route('positions.store') }}'"
                  method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="posModal.id ? 'PUT' : 'POST'">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Posisi <span style="color:#dc2626">*</span>
                    </label>
                    <input type="text" name="name" x-model="posModal.name"
                           required maxlength="100"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-400"
                           placeholder="cth: KITCHEN OUTLET">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Departemen</label>
                        <select name="department_id" x-model="posModal.department_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-400">
                            <option value="">— Belum Ditentukan —</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Level (1–10)</label>
                        <input type="number" name="level" x-model="posModal.level"
                               min="1" max="10"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-400"
                               placeholder="1">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Min</label>
                        <input type="number" name="salary_min" x-model="posModal.salary_min"
                               min="0" step="1000"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-400"
                               placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary Max</label>
                        <input type="number" name="salary_max" x-model="posModal.salary_max"
                               min="0" step="1000"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-400"
                               placeholder="0">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Approval Level</label>
                        <input type="number" name="approval_level" x-model="posModal.approval_level"
                               min="0"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-400"
                               placeholder="Opsional">
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1"
                                   :checked="posModal.is_active"
                                   @change="posModal.is_active = $event.target.checked"
                                   class="rounded">
                            <span class="text-sm text-gray-700">Aktif</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Tugas &amp; Tanggung Jawab (Job Description)</label>
                    <textarea name="description" x-model="posModal.description"
                              rows="6" maxlength="1000"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-400"
                              placeholder="Tuliskan tugas utama & tanggung jawab posisi ini, agar karyawan yang menjabat tahu dengan jelas pekerjaannya (opsional)"></textarea>
                </div>

                <div class="flex gap-2 justify-end pt-1">
                    <button type="button" @click="posModal.close()"
                            class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm rounded-lg text-white font-medium"
                            style="background:#059669">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- MODAL: IMPORT                                                     --}}
    {{-- ================================================================ --}}
    <div x-show="importModal.show" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.45)">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6"
             x-transition
             @click.outside="importModal.show=false">

            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-800">Import Posisi</h3>
                <button type="button" @click="importModal.show=false"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="text-sm text-gray-600 mb-4 leading-relaxed">
                Upload file Excel (.xlsx). Kolom: <span class="font-mono text-xs" style="color:#7c3aed">nama_posisi | nama_departemen | level | salary_min | salary_max | approval_level | keterangan</span>.
                <br>
                <a href="{{ route('positions.template') }}"
                   class="font-medium underline mt-1 inline-block" style="color:#7c3aed">
                    ↓ Download template terlebih dahulu
                </a>
            </div>

            <form action="{{ route('positions.import') }}" method="POST"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <input type="file" name="file" accept=".xlsx,.xls" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" @click="importModal.show=false"
                            class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm rounded-lg text-white font-medium"
                            style="background:#7c3aed">
                        Upload Import
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function positionsPage() {
    return {
        activeTab: 'all',

        deptModal: {
            show: false,
            id: null, name: '', code: '', sort_order: 0,
            open(dept) {
                this.id         = dept ? dept.id   : null;
                this.name       = dept ? dept.name : '';
                this.code       = dept ? (dept.code || '') : '';
                this.sort_order = dept ? (dept.sort_order || 0) : 0;
                this.show       = true;
            },
            close() { this.show = false; },
        },

        posModal: {
            show: false,
            id: null, name: '', department_id: '', level: 1,
            approval_level: '', salary_min: '', salary_max: '',
            description: '', is_active: true,
            open(pos) {
                this.id             = pos ? pos.id            : null;
                this.name           = pos ? pos.name          : '';
                this.department_id  = pos && pos.department_id ? String(pos.department_id) : '';
                this.level          = pos ? (pos.level  || 1) : 1;
                this.approval_level = pos ? (pos.approval_level || '') : '';
                this.salary_min     = pos ? (pos.salary_min || '') : '';
                this.salary_max     = pos ? (pos.salary_max || '') : '';
                this.description    = pos ? (pos.description || '') : '';
                this.is_active      = pos ? !!pos.is_active : true;
                this.show           = true;
            },
            close() { this.show = false; },
        },

        importModal: { show: false },
    };
}
</script>
@endsection
