@extends('layouts.app')
@section('title', 'Review Nama Karyawan')
@section('content')

<div class="space-y-4 max-w-5xl mx-auto">

    {{-- Header --}}
    <div>
        <div class="flex items-center gap-2 mb-1">
            <a href="{{ route('finance.bpjs-reconciliation.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">← Daftar Tagihan</a>
            <span class="text-gray-300">›</span>
            <span class="text-sm text-gray-500">Step 2 dari 2</span>
        </div>
        <h1 class="text-2xl font-semibold text-slate-800">
            Review Nama Karyawan
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            {{ $bill->source_file_name }}
            @if($bill->npp) · NPP: <span class="font-mono">{{ $bill->npp }}</span>@endif
            · Periode: {{ $bill->periode_label }}
            · <strong>{{ $rows->count() }} baris</strong>
        </p>
    </div>

    {{-- Banner warning --}}
    @if($warningCount > 0)
    <div style="background:#FEF9C3; border:1.5px solid #F59E0B; border-radius:10px;
                padding:12px 16px; display:flex; align-items:center; gap:10px;">
        <span style="font-size:20px;">⚠️</span>
        <div>
            <div style="font-weight:700; color:#92400E; font-size:14px;">
                Ditemukan {{ $warningCount }} nama yang perlu diperiksa
            </div>
            <div style="font-size:12px; color:#92400E; margin-top:2px;">
                Nama berwarna kuning = 1 kata (kemungkinan terpotong) ·
                Nama berwarna merah = total iuran tidak wajar
            </div>
        </div>
    </div>
    @endif

    @if(session('success'))
    <div style="background:#DCFCE7; border:1px solid #86EFAC; border-radius:8px;
                padding:10px 14px; color:#166534; font-size:13px;">
        {{ session('success') }}
    </div>
    @endif

    {{-- Instruksi --}}
    <p class="text-sm text-slate-600">
        Klik nama atau NIK untuk mengedit langsung. Kalau nama & NIK ketuker sama baris sebelah (akibat nama yang patah 2 baris di PDF), pakai tombol ▲▼ untuk tukar NIK dengan baris tetangga — lebih cepat daripada ketik ulang 16 digit.
    </p>

    {{-- Tabel --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#f8fafc; color:#64748b;
                           border-bottom:2px solid #e2e8f0;"
                    class="text-left text-xs uppercase tracking-wide">
                    <th class="px-4 py-3 w-10">No</th>
                    <th class="px-4 py-3 w-36">NIK</th>
                    <th class="px-4 py-3">Nama Karyawan</th>
                    <th class="px-4 py-3 w-36 text-right">Total Iuran</th>
                    <th class="px-4 py-3 w-24 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($rows as $i => $row)
                <tr class="hover:bg-slate-50 transition-colors" id="row-{{ $row->id }}">
                    <td class="px-4 py-2.5 text-slate-400 text-xs">{{ $i + 1 }}</td>
                    <td class="px-4 py-2.5">
                        <div style="display:flex; align-items:center; gap:4px;">
                            <div class="flex flex-col" style="gap:1px;">
                                <button type="button" class="swap-nik-btn" data-id="{{ $row->id }}" data-direction="up"
                                        title="Tukar NIK dengan baris di atas"
                                        style="line-height:1; font-size:9px; padding:0 3px; border:1px solid #E2E8F0; border-radius:3px; background:#F8FAFC; cursor:pointer;">▲</button>
                                <button type="button" class="swap-nik-btn" data-id="{{ $row->id }}" data-direction="down"
                                        title="Tukar NIK dengan baris di bawah"
                                        style="line-height:1; font-size:9px; padding:0 3px; border:1px solid #E2E8F0; border-radius:3px; background:#F8FAFC; cursor:pointer;">▼</button>
                            </div>
                            <div class="nik-cell"
                                 data-id="{{ $row->id }}"
                                 data-original="{{ $row->nik }}"
                                 style="cursor:pointer; padding:5px 8px; border-radius:6px; border:2px solid transparent;
                                        display:inline-flex; align-items:center; gap:4px;"
                                 title="Klik untuk edit NIK">
                                <span class="nik-text font-mono text-xs text-slate-600">{{ $row->nik ?? '—' }}</span>
                                <span class="edit-hint text-slate-300 text-xs">✏️</span>
                            </div>
                        </div>
                    </td>

                    {{-- Nama: inline editable --}}
                    <td class="px-4 py-2.5">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <div class="nama-cell"
                                 data-id="{{ $row->id }}"
                                 data-original="{{ $row->nama }}"
                                 style="cursor:pointer; padding:5px 8px;
                                        border-radius:6px; border:2px solid transparent;
                                        display:inline-flex; align-items:center; gap:4px;
                                        max-width:100%;
                                        {{ $row->warning === 'fragment' ? 'background:#FEF9C3; border-color:#F59E0B;' : '' }}
                                        {{ $row->warning === 'total'    ? 'background:#FEE2E2; border-color:#EF4444;' : '' }}"
                                 title="Klik untuk edit">

                                @if($row->warning)
                                <span title="{{ $row->warning === 'fragment'
                                               ? 'Nama hanya 1 kata — kemungkinan terpotong'
                                               : 'Total iuran tidak wajar — kemungkinan grand total tersedot' }}">
                                    ⚠️
                                </span>
                                @endif

                                <span class="nama-text font-medium text-slate-800">{{ $row->nama }}</span>
                                <span class="edit-hint text-slate-300 text-xs">✏️</span>
                            </div>

                            @if($row->warning === 'fragment')
                            <button type="button" class="merge-up-btn" data-id="{{ $row->id }}"
                                    title="Gabung nama ini ke baris di atasnya, lalu geser nama baris di bawahnya naik satu (NIK/Total tidak berubah) — untuk nama yang patah 2 baris di PDF"
                                    style="font-size:10px; padding:3px 8px; border:1px solid #F59E0B; border-radius:6px; background:#FFFBEB; color:#92400E; cursor:pointer; white-space:nowrap;">
                                🔗 Gabung ke atas
                            </button>
                            @endif
                        </div>
                    </td>

                    <td class="px-4 py-2.5 text-right font-mono text-xs
                               {{ $row->warning === 'total' ? 'text-red-600 font-bold' : 'text-slate-600' }}">
                        @if($row->warning === 'total')<span class="mr-1">⚠️</span>@endif
                        Rp {{ number_format($row->total_iuran, 0, ',', '.') }}
                    </td>

                    <td class="px-4 py-2.5 text-center">
                        <span class="row-status text-xs text-slate-400"
                              id="status-{{ $row->id }}">Original</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Action buttons --}}
    <div style="display:flex; justify-content:space-between; align-items:center;
                padding:16px 0; flex-wrap:wrap; gap:12px;">
        <a href="{{ route('finance.bpjs-reconciliation.show', $bill->id) }}"
           class="text-sm text-slate-500 hover:text-slate-700">
            ← Lewati, langsung ke detail tagihan
        </a>

        <form method="POST"
              action="{{ route('finance.bpjs-reconciliation.confirm-review', $bill->id) }}">
            @csrf
            <button type="submit"
                    style="background:#059669; color:white; border:none;
                           padding:10px 24px; border-radius:10px;
                           font-size:14px; font-weight:700; cursor:pointer;">
                ✓ Konfirmasi &amp; Jalankan Rekonsiliasi NIK
            </button>
        </form>
    </div>

</div>

<script>
document.querySelectorAll('.nama-cell').forEach(function(cell) {
    cell.addEventListener('click', function() {
        if (this.querySelector('input')) return;

        const rowId   = this.dataset.id;
        const current = this.querySelector('.nama-text').textContent.trim();

        this.innerHTML = `
            <input type="text"
                   value="${current}"
                   class="nama-input"
                   style="width:320px; max-width:100%; padding:5px 10px;
                          border:2px solid #7C3AED; border-radius:6px;
                          font-size:13px; font-weight:600; outline:none;"
                   data-id="${rowId}"
                   data-original="${current}">
        `;

        const input = this.querySelector('input');
        input.focus();
        input.select();

        const cell = this;

        const restoreOriginal = () => {
            cell.innerHTML = `
                <span class="nama-text font-medium text-slate-800">${current}</span>
                <span class="edit-hint text-slate-300 text-xs">✏️</span>
            `;
        };

        const saveEdit = () => {
            const newNama = input.value.trim().toUpperCase();
            const oldNama = input.dataset.original;

            if (!newNama || newNama === oldNama) {
                restoreOriginal();
                return;
            }

            fetch(`/finance/bpjs-reconciliation/rows/${rowId}/update-nama`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ nama: newNama }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cell.innerHTML = `
                        <span class="nama-text font-medium" style="color:#059669;">
                            ✓ ${data.new}
                        </span>
                        <span style="font-size:10px; color:#059669; margin-left:4px;">Diubah</span>
                    `;
                    cell.style.borderColor = '#86EFAC';
                    cell.style.background  = '#F0FDF4';

                    const statusEl = document.getElementById('status-' + rowId);
                    if (statusEl) {
                        statusEl.textContent = 'Diubah';
                        statusEl.style.color = '#059669';
                        statusEl.style.fontWeight = '600';
                    }
                } else {
                    restoreOriginal();
                }
            })
            .catch(() => restoreOriginal());
        };

        input.addEventListener('blur', saveEdit);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter')  { e.preventDefault(); this.blur(); }
            if (e.key === 'Escape') { this.dataset.original = current; this.blur(); }
        });
    });
});

// ── NIK inline edit (mirrors nama-cell above) ──────────────────────────────
document.querySelectorAll('.nik-cell').forEach(function(cell) {
    cell.addEventListener('click', function() {
        if (this.querySelector('input')) return;

        const rowId   = this.dataset.id;
        const current = this.querySelector('.nik-text').textContent.trim();

        this.innerHTML = `
            <input type="text"
                   value="${current === '—' ? '' : current}"
                   class="nik-input"
                   maxlength="16"
                   style="width:180px; max-width:100%; padding:5px 10px;
                          border:2px solid #7C3AED; border-radius:6px;
                          font-size:13px; font-family:monospace; outline:none;"
                   data-id="${rowId}"
                   data-original="${current}">
        `;

        const input = this.querySelector('input');
        input.focus();
        input.select();
        const cell = this;

        const restoreOriginal = () => {
            cell.innerHTML = `
                <span class="nik-text font-mono text-xs text-slate-600">${current}</span>
                <span class="edit-hint text-slate-300 text-xs">✏️</span>
            `;
        };

        const saveEdit = () => {
            const newNik = input.value.trim();
            const oldNik = input.dataset.original;

            if (!newNik || newNik === oldNik) { restoreOriginal(); return; }
            if (!/^\d{16}$/.test(newNik)) {
                alert('NIK harus 16 digit angka.');
                restoreOriginal();
                return;
            }

            fetch(`/finance/bpjs-reconciliation/rows/${rowId}/update-nik`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ nik: newNik }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cell.innerHTML = `
                        <span class="nik-text font-mono text-xs" style="color:#059669;">✓ ${data.new}</span>
                    `;
                    cell.style.borderColor = '#86EFAC';
                    cell.style.background  = '#F0FDF4';
                } else {
                    restoreOriginal();
                }
            })
            .catch(() => restoreOriginal());
        };

        input.addEventListener('blur', saveEdit);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter')  { e.preventDefault(); this.blur(); }
            if (e.key === 'Escape') { this.dataset.original = current; this.blur(); }
        });
    });
});

// ── Gabung nama ke baris atas (untuk baris "hantu" hasil nama patah) ───────
document.querySelectorAll('.merge-up-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const rowId = this.dataset.id;

        if (!confirm('Gabung nama baris ini ke baris di atasnya? Kolom Nama di semua baris di bawahnya akan naik satu posisi (seperti geser sel di Excel) — NIK, Total Iuran, dan baris itu sendiri TIDAK dihapus/berubah. Baris paling akhir akan kosong dan perlu diisi manual. Tindakan ini tidak bisa dibatalkan otomatis.')) {
            return;
        }

        fetch(`/finance/bpjs-reconciliation/rows/${rowId}/merge-up`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Gagal menggabung baris.');
                return;
            }
            // Reload supaya nomor urut, status warning, dan total baris di
            // header ikut ter-update konsisten dari server.
            window.location.reload();
        })
        .catch(() => alert('Gagal menggabung baris, coba lagi.'));
    });
});

// ── Swap NIK dengan baris tetangga ──────────────────────────────────────────
document.querySelectorAll('.swap-nik-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const rowId    = this.dataset.id;
        const direction = this.dataset.direction;

        fetch(`/finance/bpjs-reconciliation/rows/${rowId}/swap-nik`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ direction: direction }),
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Gagal menukar NIK.');
                return;
            }
            const rowCell = document.querySelector(`.nik-cell[data-id="${rowId}"] .nik-text`);
            if (rowCell) { rowCell.textContent = data.row_nik; }
            document.querySelector(`.nik-cell[data-id="${rowId}"]`).dataset.original = data.row_nik;

            const neighborCell = document.querySelector(`.nik-cell[data-id="${data.neighbor_id}"] .nik-text`);
            if (neighborCell) { neighborCell.textContent = data.neighbor_nik; }
            document.querySelector(`.nik-cell[data-id="${data.neighbor_id}"]`).dataset.original = data.neighbor_nik;
        })
        .catch(() => alert('Gagal menukar NIK, coba lagi.'));
    });
});
</script>

@endsection
