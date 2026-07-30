@extends('layouts.app')

@section('content')
@php
    $meta = $contract->meta_json ?? [];
    $reason = (string) ($meta['rejection_reason'] ?? '-');
@endphp
<div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Review Kontrak: {{ $contract->contract_number }}</h1>
            <p class="text-sm text-slate-600">Kandidat: {{ $contract->candidate?->full_name }} ({{ $contract->candidate?->email ?: '-' }})</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('hrd.contracts.download', ['contract' => $contract, 'variant' => 'original']) }}" class="px-3 py-2 rounded border text-sm">Download PDF Original</a>
            <a href="{{ route('hrd.contracts.download', ['contract' => $contract, 'variant' => 'signed']) }}" class="px-3 py-2 rounded border text-sm">Download PDF Signed</a>
            <a href="{{ route('hrd.contracts.index') }}" class="px-3 py-2 rounded border text-sm">Kembali</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white border rounded-lg p-4 space-y-4">
            <div>
                <div class="text-sm font-medium mb-2">Dokumen</div>
                <a href="{{ route('hrd.contracts.download', ['contract' => $contract, 'variant' => 'original']) }}" class="underline text-sm">Download PDF Original</a>
                @if($contract->pdf_path_signed)
                    <span class="mx-1">|</span>
                    <a href="{{ route('hrd.contracts.download', ['contract' => $contract, 'variant' => 'signed']) }}" class="underline text-sm">Download PDF Signed</a>
                @endif
            </div>

            <div>
                <div class="text-sm font-medium mb-2">Signature Kandidat</div>
                @if($contract->latestCandidateSignature?->signature_image_path)
                    <img src="{{ asset('storage/' . ltrim($contract->latestCandidateSignature->signature_image_path, '/')) }}" alt="Signature" class="max-h-40 border rounded">
                @else
                    <div class="text-sm text-slate-500">Belum ada signature kandidat.</div>
                @endif
            </div>

            <div>
                <div class="text-sm font-medium mb-2">Materai</div>
                @if($contract->latestStamp)
                    <div class="text-sm">Tipe: {{ $contract->latestStamp->stamp_type }}</div>
                    <div class="text-sm">Nomor: {{ $contract->latestStamp->stamp_number ?: '-' }}</div>
                    @if($contract->latestStamp->stamp_proof_path)
                        <a href="{{ asset('storage/' . ltrim($contract->latestStamp->stamp_proof_path, '/')) }}" class="underline text-sm" target="_blank">Lihat Bukti Materai</a>
                    @endif
                @else
                    <div class="text-sm text-slate-500">Belum ada data materai.</div>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border rounded-lg p-4 text-sm space-y-2">
                <div><b>Status:</b> {{ strtoupper($contract->status) }}</div>
                <div><b>Sent:</b> {{ $contract->sent_at?->format('d/m/Y H:i:s') ?: '-' }}</div>
                <div><b>Viewed:</b> {{ $contract->viewed_at?->format('d/m/Y H:i:s') ?: '-' }}</div>
                <div><b>Submitted:</b> {{ $contract->submitted_at?->format('d/m/Y H:i:s') ?: '-' }}</div>
                <div><b>Reviewed:</b> {{ $contract->reviewed_at?->format('d/m/Y H:i:s') ?: '-' }}</div>
                <div><b>Alasan Reject:</b> {{ $reason }}</div>
            </div>

            @if(in_array($contract->status, ['submitted','hr_review'], true))
                <form method="POST" action="{{ route('hrd.contracts.review', $contract) }}" class="bg-white border rounded-lg p-4 space-y-3">
                    @csrf
                    <div class="text-sm font-semibold">Review HRD</div>
                    <textarea name="review_reason" rows="3" class="w-full border rounded px-3 py-2 text-sm" placeholder="Wajib diisi jika reject">{{ old('review_reason') }}</textarea>
                    <div class="grid grid-cols-2 gap-2">
                        <button name="decision" value="approve" class="px-3 py-2 rounded bg-emerald-600 text-white text-sm">Approve</button>
                        <button name="decision" value="reject" class="px-3 py-2 rounded bg-rose-600 text-white text-sm">Reject</button>
                    </div>
                </form>
            @endif

            <div class="bg-white border rounded-lg p-4">
                <div class="text-sm font-semibold mb-2">Timeline Status</div>
                <div class="space-y-2 text-sm">
                    @php($history = is_array(data_get($meta, 'status_history')) ? data_get($meta, 'status_history') : [])
                    @forelse(array_reverse($history) as $item)
                        <div class="border rounded p-2">
                            <div class="font-medium uppercase">{{ data_get($item, 'status', '-') }}</div>
                            <div class="text-xs text-slate-500">{{ data_get($item, 'at', '-') }}</div>
                            @if(data_get($item, 'note'))
                                <div class="text-xs mt-1">{{ data_get($item, 'note') }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-xs text-slate-500">Belum ada histori status.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
