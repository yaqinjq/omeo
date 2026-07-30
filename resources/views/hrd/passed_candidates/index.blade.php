@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Kirim Kontrak Kandidat Accepted</h1>
            <p class="text-sm text-slate-600">Workbench operasional untuk mengirim kontrak Daily Worker ke kandidat accepted tanpa membuka detail kandidat satu per satu.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('candidates.index', ['tab' => 'done']) }}" class="px-3 py-2 rounded border text-sm">Kembali ke Recruitment Accepted</a>
            <a href="{{ route('hrd.contracts.index') }}" class="px-3 py-2 rounded border text-sm">Inbox Review Kontrak</a>
        </div>
    </div>

    <div id="guideBox" class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
        <div class="font-semibold mb-1">Guide Step 6</div>
        <ol class="list-decimal ml-5 space-y-1">
            <li>Pilih template aktif atau template tertentu.</li>
            <li>Centang kandidat lalu klik "Kirim Kontrak (Bulk)".</li>
            <li>Untuk per kandidat, klik tombol "Kirim Satuan".</li>
            <li>Monitor status di kolom "Status Kontrak" dan halaman Inbox Review Kontrak.</li>
        </ol>
        <button type="button" id="closeGuide" class="mt-2 text-xs underline">Tutup panduan</button>
    </div>

    <form method="GET" class="bg-white border rounded-lg p-4 flex flex-col md:flex-row gap-3 md:items-center">
        <input
            type="text"
            name="search"
            value="{{ $search }}"
            placeholder="Cari nama / email / NIK"
            class="w-full md:max-w-md border rounded px-3 py-2"
        >
        <button class="px-4 py-2 rounded bg-slate-900 text-white">Cari</button>
        @if($search !== '')
            <a href="{{ route('hrd.passed-candidates.index') }}" class="px-4 py-2 rounded border">Reset</a>
        @endif
    </form>

    <form method="POST" action="{{ route('hrd.contracts.send') }}" class="bg-white border rounded-lg p-4 space-y-3" id="bulkSendForm">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm mb-1">Mode Template</label>
                <select name="use_active_template" id="useActiveTemplate" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="1">Template Aktif</option>
                    <option value="0">Pilih Template Tertentu</option>
                </select>
            </div>
            <div>
                <label class="block text-sm mb-1">Template Kontrak</label>
                <select name="template_id" id="templateSelect" class="w-full border rounded px-3 py-2 text-sm" disabled>
                    <option value="">-- Pilih Template --</option>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}{{ $template->is_active ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-4 py-2 rounded bg-emerald-600 text-white text-sm">Kirim Kontrak (Bulk)</button>
            </div>
        </div>
        <p class="text-xs text-slate-500">Catatan: kandidat tanpa akun login atau kandidat dengan kontrak aktif akan dilewati otomatis.</p>

        <div class="overflow-x-auto border rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3 text-left w-10"><input type="checkbox" id="checkAll"></th>
                        <th class="p-3 text-left">Nama</th>
                        <th class="p-3 text-left">Email</th>
                        <th class="p-3 text-left">NIK</th>
                        <th class="p-3 text-left">Tanggal Diterima</th>
                        <th class="p-3 text-left">Posisi</th>
                        <th class="p-3 text-left">Outlet</th>
                        <th class="p-3 text-left">Status Kontrak</th>
                        <th class="p-3 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($candidates as $candidate)
                        @php
                            $statusMap = [
                                'belum_dikirim' => ['Belum dikirim', 'bg-slate-100 text-slate-700'],
                                'sent' => ['Terkirim', 'bg-blue-100 text-blue-700'],
                                'viewed' => ['Dibuka Kandidat', 'bg-indigo-100 text-indigo-700'],
                                'awaiting_stamp' => ['Menunggu Materai', 'bg-sky-100 text-sky-700'],
                                'awaiting_signature' => ['Menunggu Tanda Tangan', 'bg-cyan-100 text-cyan-700'],
                                'hr_review' => ['Sedang Direview HRD', 'bg-orange-100 text-orange-700'],
                                'submitted' => ['Menunggu Review HRD', 'bg-amber-100 text-amber-700'],
                                'approved' => ['Disetujui', 'bg-emerald-100 text-emerald-700'],
                                'rejected' => ['Ditolak/Revisi', 'bg-rose-100 text-rose-700'],
                            ];
                            [$statusLabel, $statusClass] = $statusMap[$candidate->latest_contract_status] ?? ['-', 'bg-slate-100 text-slate-700'];
                        @endphp
                        <tr class="border-t align-top">
                            <td class="p-3"><input type="checkbox" name="candidate_ids[]" value="{{ $candidate->id }}" class="candidate-checkbox rounded border-slate-300"></td>
                            <td class="p-3 font-medium">{{ $candidate->full_name }}</td>
                            <td class="p-3">{{ $candidate->email ?: '-' }}</td>
                            <td class="p-3">{{ $candidate->nik ?: '-' }}</td>
                            <td class="p-3">{{ $candidate->accepted_label }}</td>
                            <td class="p-3">{{ $candidate->position_name }}</td>
                            <td class="p-3">{{ $candidate->outlet_name }}</td>
                            <td class="p-3"><span class="px-2 py-1 rounded text-xs {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="p-3 space-x-2 whitespace-nowrap">
                                <a href="{{ route('candidates.show', $candidate) }}" class="underline">Profil</a>
                                @if($candidate->latest_contract_id)
                                    <a href="{{ route('hrd.contracts.show', $candidate->latest_contract_id) }}" class="underline">Detail Kontrak</a>
                                @endif
                                <button type="button" class="px-2 py-1 rounded border text-xs" onclick="sendSingle({{ $candidate->id }})">Kirim Satuan</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-6 text-center text-slate-500">Belum ada kandidat lolos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div>{{ $candidates->links() }}</div>
</div>

<form method="POST" action="{{ route('hrd.contracts.send') }}" id="singleSendForm" class="hidden">
    @csrf
    <input type="hidden" name="candidate_ids[]" id="singleCandidateId">
    <input type="hidden" name="use_active_template" id="singleUseActive" value="1">
    <input type="hidden" name="template_id" id="singleTemplateId">
</form>

<script>
(() => {
    const guideKey = 'dw-contract-guide-dismissed';
    const guideBox = document.getElementById('guideBox');
    const closeGuide = document.getElementById('closeGuide');
    if (localStorage.getItem(guideKey) === '1' && guideBox) {
        guideBox.classList.add('hidden');
    }
    closeGuide?.addEventListener('click', () => {
        localStorage.setItem(guideKey, '1');
        guideBox?.classList.add('hidden');
    });

    const checkAll = document.getElementById('checkAll');
    const checkboxes = Array.from(document.querySelectorAll('.candidate-checkbox'));
    checkAll?.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => { checkbox.checked = checkAll.checked; });
    });

    const useActive = document.getElementById('useActiveTemplate');
    const templateSelect = document.getElementById('templateSelect');

    const syncTemplateMode = () => {
        const isActiveMode = useActive?.value === '1';
        if (templateSelect) {
            templateSelect.disabled = isActiveMode;
            if (isActiveMode) {
                templateSelect.value = '';
            }
        }
    };

    useActive?.addEventListener('change', syncTemplateMode);
    syncTemplateMode();

    window.sendSingle = (candidateId) => {
        const singleForm = document.getElementById('singleSendForm');
        const singleCandidateId = document.getElementById('singleCandidateId');
        const singleUseActive = document.getElementById('singleUseActive');
        const singleTemplateId = document.getElementById('singleTemplateId');
        const isActiveMode = useActive?.value === '1';

        singleCandidateId.value = candidateId;
        singleUseActive.value = isActiveMode ? '1' : '0';
        singleTemplateId.value = isActiveMode ? '' : (templateSelect?.value || '');

        if (!isActiveMode && !singleTemplateId.value) {
            alert('Pilih template tertentu terlebih dahulu.');
            return;
        }

        singleForm.submit();
    };
})();
</script>
@endsection


