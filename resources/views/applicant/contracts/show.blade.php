@extends('layouts.app')

@section('content')
@php
    $meta = $contract->meta_json ?? [];
    $history = is_array(data_get($meta, 'status_history')) ? data_get($meta, 'status_history') : [];
    $rejectReason = (string) (data_get($meta, 'rejection_reason') ?? '-');
@endphp
<div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Kontrak: {{ $contract->contract_number }}</h1>
            <p class="text-sm text-slate-600">Status saat ini: <span class="font-semibold uppercase">{{ $contract->status }}</span></p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('applicant.contracts.download', $contract) }}" class="px-3 py-2 rounded border text-sm">Download PDF Original</a>
            <a href="{{ route('applicant.contracts.index') }}" class="px-3 py-2 rounded border text-sm">Kembali</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white border rounded-lg p-4 space-y-4">
            <div class="text-sm text-slate-700">
                @if($contract->status === 'approved')
                    Kontrak Anda sudah disetujui HRD dan siap diproses ke tahap probation.
                @elseif($contract->status === 'rejected')
                    Kontrak ditolak HRD. Alasan: {{ $rejectReason }}
                @else
                    Isi kontrak bersifat read-only, Anda hanya dapat mengisi materai dan tanda tangan.
                @endif
            </div>

            <div class="bg-slate-50 border rounded p-3 text-xs space-y-1">
                <div><b>Nama:</b> {{ data_get($meta, 'candidate_name', '-') }}</div>
                <div><b>NIK:</b> {{ data_get($meta, 'candidate_nik', '-') }}</div>
                <div><b>Posisi:</b> {{ data_get($meta, 'position_name', '-') }}</div>
                <div><b>Outlet:</b> {{ data_get($meta, 'outlet_name', '-') }}</div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border rounded-lg p-4 text-sm space-y-2">
                <div><b>Sent:</b> {{ $contract->sent_at?->format('d/m/Y H:i:s') ?: '-' }}</div>
                <div><b>Viewed:</b> {{ $contract->viewed_at?->format('d/m/Y H:i:s') ?: '-' }}</div>
                <div><b>Submitted:</b> {{ $contract->submitted_at?->format('d/m/Y H:i:s') ?: '-' }}</div>
                <div><b>Approved:</b> {{ $contract->approved_at?->format('d/m/Y H:i:s') ?: '-' }}</div>
            </div>

            @if(in_array($contract->status, ['sent','viewed','awaiting_stamp','rejected'], true))
                <form method="POST" action="{{ route('applicant.contracts.stamp', $contract) }}" enctype="multipart/form-data" class="bg-white border rounded-lg p-4 space-y-3">
                    @csrf
                    <div class="text-sm font-semibold">1) Materai Elektronik (Wajib)</div>
                    <input type="text" name="stamp_number" value="{{ old('stamp_number', $contract->latestStamp?->stamp_number) }}" class="w-full border rounded px-3 py-2 text-sm" placeholder="Nomor materai (opsional jika upload bukti)">
                    <input type="file" name="stamp_file" class="w-full border rounded px-3 py-2 text-sm" accept=".jpg,.jpeg,.png,.pdf">
                    <label class="flex items-start gap-2 text-xs">
                        <input type="checkbox" name="stamp_confirmed" value="1" class="mt-1" required>
                        <span>Saya menyatakan data materai benar.</span>
                    </label>
                    <button class="w-full px-4 py-2 rounded bg-slate-900 text-white text-sm">Simpan Materai</button>
                </form>
            @endif

            @if($contract->status === 'awaiting_signature')
                <form method="POST" action="{{ route('applicant.contracts.submit', $contract) }}" class="bg-white border rounded-lg p-4 space-y-3" id="signForm">
                    @csrf
                    <div class="text-sm font-semibold">2) Tanda Tangan Digital (Blue Ink)</div>
                    <canvas id="signaturePad" class="w-full border rounded" height="180"></canvas>
                    <input type="hidden" name="signature_data" id="signatureData">
                    <button type="button" id="clearSignature" class="px-2 py-1 rounded border text-xs">Hapus Tanda Tangan</button>
                    <button type="submit" class="w-full px-4 py-2 rounded bg-blue-700 text-white text-sm">Kirim ke HRD</button>
                </form>
            @endif

            @if(in_array($contract->status, ['submitted','hr_review','approved'], true))
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-sm text-emerald-800">Kontrak sudah disubmit. Form materai dan tanda tangan dinonaktifkan.</div>
            @endif

            <div class="bg-white border rounded-lg p-4">
                <div class="text-sm font-semibold mb-2">Timeline</div>
                <div class="space-y-2 text-sm">
                    @forelse(array_reverse($history) as $item)
                        <div class="border rounded p-2">
                            <div class="font-medium uppercase">{{ data_get($item, 'status', '-') }}</div>
                            <div class="text-xs text-slate-500">{{ data_get($item, 'at', '-') }}</div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-500">Belum ada histori.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const canvas = document.getElementById('signaturePad');
    const clearBtn = document.getElementById('clearSignature');
    const hiddenInput = document.getElementById('signatureData');
    const form = document.getElementById('signForm');

    if (!canvas || !hiddenInput || !form) {
        return;
    }

    const ctx = canvas.getContext('2d');
    let drawing = false;

    const setupPen = () => {
        ctx.strokeStyle = '#1d4ed8';
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    };

    setupPen();

    const getPos = (event) => {
        const rect = canvas.getBoundingClientRect();
        const source = event.touches ? event.touches[0] : event;
        return { x: source.clientX - rect.left, y: source.clientY - rect.top };
    };

    const start = (event) => {
        drawing = true;
        const pos = getPos(event);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        event.preventDefault();
    };

    const move = (event) => {
        if (!drawing) return;
        const pos = getPos(event);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        event.preventDefault();
    };

    const end = () => { drawing = false; };

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    clearBtn?.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hiddenInput.value = '';
    });

    form.addEventListener('submit', (event) => {
        const pixels = new Uint32Array(ctx.getImageData(0, 0, canvas.width, canvas.height).data.buffer);
        const hasInk = pixels.some((p) => p !== 0);
        if (!hasInk) {
            event.preventDefault();
            alert('Tanda tangan digital wajib diisi.');
            return;
        }
        hiddenInput.value = canvas.toDataURL('image/png');
    });
})();
</script>
@endsection
