@extends('layouts.app')

@section('content')
@php
    $activeTab = $tab ?? 'incomplete';
    $tabCounts = $counts ?? ['incomplete' => 0, 'unverified' => 0, 'verified' => 0];
    $filters = $filters ?? ['search' => request('search'), 'sort' => request('sort', 'updated_at'), 'order' => request('order', 'desc')];
    $tabLinks = [
        'incomplete' => 'Belum Lengkap',
        'unverified' => 'Complete-Unverified',
        'verified' => 'Verified/Shortlisted',
    ];
    $stageClasses = [
        'incomplete' => 'bg-amber-50 text-amber-700 border-amber-200',
        'unverified' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'verified' => 'bg-blue-50 text-blue-700 border-blue-200',
    ];
    $governanceClasses = [
        'active' => 'bg-slate-100 text-slate-700 border-slate-200',
        'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
        'blacklisted' => 'bg-slate-900 text-white border-slate-900',
        'archived' => 'bg-slate-200 text-slate-700 border-slate-300',
    ];
@endphp
<div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-10 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Talent Pool Applicants</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Governance applicant sebelum masuk pipeline Recruitment Kandidat.</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-800 max-w-md">
                Applicant yang lolos administrasi akan dibuatkan kandidat recruitment dengan status <span class="font-semibold">shortlisted</span> agar langsung masuk tab <span class="font-semibold">Baru Lolos Administrasi</span>.
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex flex-wrap gap-2 md:gap-4">
                @foreach($tabLinks as $key => $label)
                    @php
                        $isActive = $activeTab === $key;
                        $count = $tabCounts[$key] ?? 0;
                    @endphp
                    <a href="{{ route('hrd.applicants.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 text-sm font-bold {{ $isActive ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        <span>{{ $label }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $isActive ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <form method="GET" action="{{ route('hrd.applicants.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <div class="md:col-span-2">
                    <label class="text-xs font-bold text-gray-500 uppercase">Cari Kandidat</label>
                    <div class="relative mt-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 pl-10 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Nama, Email, atau NIK...">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase">Sort</label>
                    <select name="sort" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="updated_at" @selected(($filters['sort'] ?? 'updated_at') === 'updated_at')>Tanggal update</option>
                        <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Nama kandidat</option>
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700 transition">Terapkan Filter</button>
                </div>
            </form>
        </div>

        @if(!empty($bulkActions))
            <form method="POST" action="{{ route('hrd.applicants.bulk-action') }}" id="bulkApplicantForm" class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-4">
                @csrf
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'updated_at' }}">
                <input type="hidden" name="order" value="{{ $filters['order'] ?? 'desc' }}">
                <input type="hidden" name="selection_scope" id="bulkSelectionScope" value="page">
                <input type="hidden" name="action" id="bulkActionField" value="">

                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">Bulk Action</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Pilih kandidat dari halaman ini atau proses semua hasil filter aktif secara aman dari backend.</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="selectCurrentPageBtn" class="px-3 py-2 rounded-lg border text-xs font-semibold text-gray-700 hover:bg-gray-50">Select all current page</button>
                        <button type="button" id="selectFilteredBtn" class="px-3 py-2 rounded-lg border text-xs font-semibold text-blue-700 border-blue-200 bg-blue-50 hover:bg-blue-100">Select all filtered results</button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div id="bulkSelectionInfo" class="text-sm text-gray-600 dark:text-gray-300">0 applicant dipilih dari halaman ini.</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($bulkActions as $action)
                            <button type="submit" data-bulk-action="{{ $action['value'] }}" class="bulk-action-button px-3 py-2 rounded-lg text-xs font-semibold border border-gray-300 text-gray-700 hover:bg-gray-50">{{ $action['label'] }}</button>
                        @endforeach
                    </div>
                </div>
            </form>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-300 uppercase text-xs font-bold">
                        <tr>
                            @if(!empty($bulkActions))
                                <th class="px-4 py-4 w-10"><input type="checkbox" id="selectAllVisible" class="rounded border-slate-300"></th>
                            @endif
                            <th class="px-4 py-4">Kandidat</th>
                            <th class="px-4 py-4">Kontak</th>
                            <th class="px-4 py-4">Stage</th>
                            <th class="px-4 py-4">Governance</th>
                            <th class="px-4 py-4">Umur Profil</th>
                            <th class="px-4 py-4">Update</th>
                            <th class="px-4 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($applicants as $item)
                            @php
                                $candidate = $item->linked_candidate;
                                $stage = $item->talent_pool_stage;
                                $stageLabel = \App\Models\ApplicantProfile::TALENT_POOL_STAGE_LABELS[$stage] ?? ucfirst(str_replace('_', ' ', $stage));
                                $stageClass = $stageClasses[$stage] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                                $governanceClass = $governanceClasses[$item->governance_status ?? 'active'] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                                $initials = collect(explode(' ', trim((string) ($item->full_name ?: 'NA'))))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition align-top">
                                @if(!empty($bulkActions))
                                    <td class="px-4 py-4"><input type="checkbox" value="{{ $item->id }}" class="applicant-checkbox rounded border-slate-300" @disabled(!$item->can_govern)></td>
                                @endif
                                <td class="px-4 py-4">
                                    <div class="flex items-start gap-3">
                                        @if($item->photo_path)
                                            <img class="h-11 w-11 rounded-full object-cover border border-gray-200" src="{{ asset('storage/'.$item->photo_path) }}" alt="">
                                        @else
                                            <div class="h-11 w-11 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">{{ $initials !== '' ? $initials : 'NA' }}</div>
                                        @endif
                                        <div class="min-w-0 space-y-1.5">
                                            <div>
                                                <div class="font-bold text-gray-900 dark:text-white">{{ $item->full_name ?: '-' }}</div>
                                                <div class="text-xs text-gray-500">NIK: {{ $item->ktp_number ?: '-' }}</div>
                                            </div>

                                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                                <div><span class="font-semibold text-slate-700">Melamar sebagai:</span> {{ $item->applied_position_name ?: '-' }}</div>
                                                <div><span class="font-semibold text-slate-700">Departemen:</span> {{ $item->applied_department_name ?: '-' }}</div>
                                                <div><span class="font-semibold text-slate-700">Outlet:</span> {{ $item->applied_outlet_name ?: '-' }}</div>
                                            </div>

                                            @if($candidate)
                                                <div class="text-xs text-blue-600">Recruitment candidate #{{ $candidate->id }} â€¢ {{ ucfirst($candidate->status) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">
                                    <div>{{ $item->user?->email ?: data_get($item->personal_json, 'email', '-') }}</div>
                                    <div class="text-xs text-gray-500">{{ $item->whatsapp ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-4"><span class="px-3 py-1 rounded-full text-xs font-bold border {{ $stageClass }}">{{ $stageLabel }}</span></td>
                                <td class="px-4 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $governanceClass }}">{{ $item->governance_status_label ?? ucfirst($item->governance_status ?? 'active') }}</span>
                                    @if($item->governance_reason)
                                        <div class="text-xs text-gray-500 mt-2 max-w-xs">{{ $item->governance_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-300">
                                    <div>{{ $item->created_at?->diffForHumans() }}</div>
                                    <div class="text-xs text-gray-500">{{ $item->profile_age_days ?? 0 }} hari</div>
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-300">{{ $item->updated_at?->diffForHumans() }}</td>
                                <td class="px-4 py-4 text-right">
                                    <div class="inline-flex items-stretch gap-1" style="line-height:0"><button type="button" onclick="window.location.href='{{ route('hrd.applicants.show', $item) }}'" title="Detail" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 transition-colors flex-shrink-0 p-0 m-0 align-middle"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>@if($item->can_govern ?? true)<form method="POST" action="{{ route('hrd.applicants.govern', $item) }}" onsubmit="return confirm('Terima kandidat ini sebagai karyawan probation?')" style="display:contents">@csrf<input type="hidden" name="action" value="accept"><button type="submit" title="Terima" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-emerald-500 hover:bg-emerald-600 text-white transition-colors flex-shrink-0 p-0 m-0 align-middle"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></button></form><form method="POST" action="{{ route('hrd.applicants.govern', $item) }}" onsubmit="return confirm('Tolak applicant ini dari Talent Pool?')" style="display:contents">@csrf<input type="hidden" name="action" value="reject"><button type="submit" title="Tolak" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-amber-50 hover:bg-amber-100 text-amber-500 border border-amber-300 transition-colors flex-shrink-0 p-0 m-0 align-middle"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></form><form method="POST" action="{{ route('hrd.applicants.govern', $item) }}" onsubmit="return confirm('Blacklist kandidat ini secara permanen?')" style="display:contents">@csrf<input type="hidden" name="action" value="blacklist"><button type="submit" title="Blacklist" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-50 hover:bg-red-100 text-red-500 border border-red-300 transition-colors flex-shrink-0 p-0 m-0 align-middle"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg></button></form><form method="POST" action="{{ route('hrd.applicants.govern', $item) }}" onsubmit="return confirm('Archive kandidat ini? Data tetap tersimpan.')" style="display:contents">@csrf<input type="hidden" name="action" value="archive"><button type="submit" title="Archive" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-500 border border-gray-300 transition-colors flex-shrink-0 p-0 m-0 align-middle"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg></button></form>@endif</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ !empty($bulkActions) ? 8 : 7 }}" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <span class="font-medium">Tidak ada data applicant pada filter ini.</span>
                                        <span class="text-xs">Coba ubah kata kunci pencarian atau pindah tab governance.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-sm text-gray-500">
                            Menampilkan {{ $applicants->firstItem() ?? 0 }}–{{ $applicants->lastItem() ?? 0 }}
                            dari {{ $applicants->total() }} pelamar
                        </span>
                        <x-per-page-selector :per-page="(int) request('per_page', 10)" />
                    </div>
                    <div>{{ $applicants->appends(request()->query())->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(!empty($bulkActions))
<script>
(function () {
    const form = document.getElementById('bulkApplicantForm');
    const actionField = document.getElementById('bulkActionField');
    const scopeField = document.getElementById('bulkSelectionScope');
    const selectAllVisible = document.getElementById('selectAllVisible');
    const selectCurrentPageBtn = document.getElementById('selectCurrentPageBtn');
    const selectFilteredBtn = document.getElementById('selectFilteredBtn');
    const infoEl = document.getElementById('bulkSelectionInfo');
    const checkboxes = Array.from(document.querySelectorAll('.applicant-checkbox'));
    const actionButtons = Array.from(document.querySelectorAll('.bulk-action-button'));

    if (!form || checkboxes.length === 0) {
        return;
    }

    const activeCheckboxes = () => checkboxes.filter((cb) => !cb.disabled);
    const selected = () => checkboxes.filter((cb) => cb.checked && !cb.disabled);

    const clearHiddenIds = () => {
        form.querySelectorAll('input[data-bulk-id="1"]').forEach((el) => el.remove());
    };

    const appendHiddenIds = () => {
        clearHiddenIds();
        selected().forEach((cb) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'profile_ids[]';
            hidden.value = cb.value;
            hidden.setAttribute('data-bulk-id', '1');
            form.appendChild(hidden);
        });
    };

    const syncInfo = () => {
        const count = selected().length;
        const visibleCount = activeCheckboxes().length;
        const isFiltered = scopeField.value === 'filtered';

        if (selectAllVisible) {
            selectAllVisible.checked = visibleCount > 0 && count === visibleCount;
            selectAllVisible.indeterminate = count > 0 && count < visibleCount;
        }

        infoEl.textContent = isFiltered
            ? `Semua hasil filter aktif akan diproses. ${count} applicant pada halaman ini sedang tercentang sebagai referensi visual.`
            : `${count} applicant dipilih dari halaman ini.`;
    };

    selectAllVisible?.addEventListener('change', function () {
        scopeField.value = 'page';
        activeCheckboxes().forEach((cb) => cb.checked = selectAllVisible.checked);
        syncInfo();
    });

    selectCurrentPageBtn?.addEventListener('click', function () {
        scopeField.value = 'page';
        activeCheckboxes().forEach((cb) => cb.checked = true);
        syncInfo();
    });

    selectFilteredBtn?.addEventListener('click', function () {
        scopeField.value = 'filtered';
        activeCheckboxes().forEach((cb) => cb.checked = true);
        syncInfo();
    });

    checkboxes.forEach((cb) => cb.addEventListener('change', function () {
        if (scopeField.value !== 'page') {
            scopeField.value = 'page';
        }
        syncInfo();
    }));

    actionButtons.forEach((button) => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const action = button.getAttribute('data-bulk-action');
            const count = selected().length;
            const isFiltered = scopeField.value === 'filtered';

            if (!action) {
                return;
            }

            if (!isFiltered && count === 0) {
                alert('Pilih minimal satu applicant untuk bulk action.');
                return;
            }

            actionField.value = action;
            appendHiddenIds();
            form.submit();
        });
    });

    syncInfo();
})();
</script>
@endif
@endsection
