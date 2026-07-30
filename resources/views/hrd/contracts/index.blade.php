@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Inbox Kontrak (HRD)</h1>
            <p class="text-sm text-slate-600">Review kontrak masuk dari kandidat Daily Worker.</p>
        </div>
        <a href="{{ route('hrd.passed-candidates.index') }}" class="px-3 py-2 rounded border text-sm text-center">Kembali ke Lolos Kandidat</a>
    </div>

    <form method="GET" class="bg-white border rounded-lg p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <input type="text" name="search" value="{{ $search }}" placeholder="Cari kandidat / nomor kontrak" class="border rounded px-3 py-2 text-sm">
        <select name="status" class="border rounded px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            @foreach(['submitted','hr_review','approved','rejected','sent','viewed','awaiting_stamp','awaiting_signature'] as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ strtoupper($s) }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 rounded bg-slate-900 text-white text-sm">Filter</button>
    </form>

    <div class="bg-white border rounded-lg overflow-hidden">
        <div class="md:hidden divide-y">
            @forelse($contracts as $contract)
                <div class="p-4 space-y-2">
                    <div class="font-semibold text-slate-900">{{ $contract->contract_number }}</div>
                    <div>
                        <div class="font-medium">{{ $contract->candidate?->full_name }}</div>
                        <div class="text-xs text-slate-500">{{ $contract->candidate?->email ?: '-' }}</div>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="px-2 py-1 rounded bg-slate-100 uppercase">{{ $contract->status }}</span>
                        <span>{{ $contract->submitted_at?->format('d/m/Y H:i') ?: '-' }}</span>
                    </div>
                    <div class="text-xs">
                        Signature:
                        @if($contract->latestCandidateSignature?->signature_image_path)
                            <span class="text-emerald-700">Ada</span>
                        @else
                            <span class="text-slate-500">-</span>
                        @endif
                    </div>
                    <details class="relative pt-1">
                        <summary class="list-none inline-flex cursor-pointer items-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Aksi</summary>
                        <div class="absolute right-0 z-20 mt-2 w-40 rounded-lg border bg-white p-2 shadow-lg">
                            <a href="{{ route('hrd.contracts.show', $contract) }}" class="block rounded px-3 py-2 text-xs hover:bg-slate-100">Review</a>
                        </div>
                    </details>
                </div>
            @empty
                <div class="p-6 text-center text-slate-500">Belum ada kontrak.</div>
            @endforelse
        </div>

        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3 text-left">No Kontrak</th>
                        <th class="p-3 text-left">Kandidat</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-left">Submit</th>
                        <th class="p-3 text-left">Signature</th>
                        <th class="p-3 text-left">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $contract)
                        <tr class="border-t align-top">
                            <td class="p-3 font-medium">{{ $contract->contract_number }}</td>
                            <td class="p-3">
                                <div class="font-medium">{{ $contract->candidate?->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $contract->candidate?->email ?: '-' }}</div>
                            </td>
                            <td class="p-3"><span class="px-2 py-1 rounded bg-slate-100 text-xs uppercase">{{ $contract->status }}</span></td>
                            <td class="p-3">{{ $contract->submitted_at?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td class="p-3">
                                @if($contract->latestCandidateSignature?->signature_image_path)
                                    <span class="text-emerald-700 text-xs">Ada</span>
                                @else
                                    <span class="text-slate-500 text-xs">-</span>
                                @endif
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                <details class="relative inline-block">
                                    <summary class="list-none inline-flex cursor-pointer items-center rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Aksi</summary>
                                    <div class="absolute right-0 z-20 mt-2 w-40 rounded-lg border bg-white p-2 shadow-lg">
                                        <a href="{{ route('hrd.contracts.show', $contract) }}" class="block rounded px-3 py-2 text-xs hover:bg-slate-100">Review</a>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-slate-500">Belum ada kontrak.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $contracts->links() }}</div>
</div>
@endsection
