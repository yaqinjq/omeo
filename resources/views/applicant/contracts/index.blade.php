@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold">Inbox Kontrak Saya</h1>
        <p class="text-sm text-slate-600">Buka kontrak, isi materai, tanda tangan tinta biru, lalu kirim ke HRD.</p>
    </div>

    <div id="guideBoxApplicant" class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
        <div class="font-semibold mb-1">Panduan Kandidat</div>
        <ol class="list-decimal ml-5 space-y-1">
            <li>Klik "Lihat" pada kontrak.</li>
            <li>Upload bukti materai atau isi nomor materai lalu simpan.</li>
            <li>Tanda tangan biru di canvas.</li>
            <li>Kirim ke HRD dan tunggu review.</li>
        </ol>
        <button type="button" id="closeGuideApplicant" class="mt-2 text-xs underline">Tutup panduan</button>
    </div>

    <div class="bg-white border rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="p-3 text-left">No Kontrak</th>
                    <th class="p-3 text-left">Template</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Terkirim</th>
                    <th class="p-3 text-left">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contracts as $contract)
                    <tr class="border-t">
                        <td class="p-3 font-medium">{{ $contract->contract_number }}</td>
                        <td class="p-3">{{ $contract->template?->name ?: '-' }}</td>
                        <td class="p-3"><span class="px-2 py-1 rounded bg-slate-100 text-xs uppercase">{{ $contract->status }}</span></td>
                        <td class="p-3">{{ $contract->sent_at?->format('d/m/Y H:i') ?: '-' }}</td>
                        <td class="p-3 whitespace-nowrap">
                            <a href="{{ route('applicant.contracts.show', $contract) }}" class="underline">Lihat</a>
                            <a href="{{ route('applicant.contracts.download', $contract) }}" class="underline ml-2">Download PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-slate-500">Belum ada kontrak yang dikirim HRD.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($contracts instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div>{{ $contracts->links() }}</div>
    @endif
</div>

<script>
(() => {
    const guideKey = 'dw-contract-guide-applicant-dismissed';
    const guideBox = document.getElementById('guideBoxApplicant');
    const closeGuide = document.getElementById('closeGuideApplicant');
    if (localStorage.getItem(guideKey) === '1' && guideBox) {
        guideBox.classList.add('hidden');
    }
    closeGuide?.addEventListener('click', () => {
        localStorage.setItem(guideKey, '1');
        guideBox?.classList.add('hidden');
    });
})();
</script>
@endsection
