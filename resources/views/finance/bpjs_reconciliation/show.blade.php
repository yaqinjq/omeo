@extends('layouts.app')
@section('content')
<div class="space-y-4">
  {{-- Header --}}
  <div class="flex items-start justify-between flex-wrap gap-3">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <a href="{{ route('finance.bpjs-reconciliation.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Daftar Tagihan</a>
      </div>
      <h1 class="text-2xl font-semibold">
        Tagihan BPJS — {{ $bill->periode_label }}
      </h1>
      <p class="text-sm text-slate-500 mt-0.5">
        {{ $bill->legalEntity?->name ?? 'Entitas belum ditautkan' }}
        @if($bill->npp) · NPP: <span class="font-mono">{{ $bill->npp }}</span>@endif
        · {{ $bill->source_file_name }}
      </p>
    </div>
    <div class="flex gap-2 flex-wrap">
      @if(in_array($bill->status, ['draft', 'matched']))
        <a href="{{ route('finance.bpjs-reconciliation.review', $bill->id) }}"
           class="px-4 py-2 rounded border border-violet-400 text-violet-700 bg-violet-50 text-sm hover:bg-violet-100">
            ✏️ Review Nama
        </a>
        <a href="{{ route('finance.bpjs-reconciliation.cross-billing', $bill->id) }}"
           class="px-4 py-2 rounded border border-blue-400 text-blue-700 bg-blue-50 text-sm hover:bg-blue-100">
            🏢 Cross-Billing
        </a>
        @if($bill->pdf_path)
        <form method="POST"
              action="{{ route('finance.bpjs-reconciliation.reparse', $bill->id) }}"
              onsubmit="return confirm('Re-parse akan membaca ulang PDF dan mengganti semua data baris. Lanjutkan?')">
          @csrf
          <button type="submit"
                  style="background:#F59E0B; color:white; border:none;
                         padding:8px 16px; border-radius:8px;
                         font-size:13px; font-weight:600; cursor:pointer;">
              🔄 Re-parse PDF
          </button>
        </form>
        @endif
        <form method="POST" action="{{ route('finance.bpjs-reconciliation.rematch', $bill) }}">
          @csrf
          <button type="submit" class="px-4 py-2 rounded border text-sm hover:bg-gray-50">
            Ulang Matching (NIK Only)
          </button>
        </form>
        @php $flaggedCount = $statusCounts->get('flagged', 0); @endphp
        @if($flaggedCount > 0)
        <form method="POST" action="{{ route('finance.bpjs-reconciliation.cleanup', $bill->id) }}">
          @csrf
          <button type="submit"
                  class="px-4 py-2 rounded border border-amber-400 text-amber-700 bg-amber-50 text-sm hover:bg-amber-100"
                  onclick="return confirm('Reset {{ $flaggedCount }} baris Flagged yang NIK-nya tidak cocok ke Not Found?')">
            Bersihkan Salah Match ({{ $flaggedCount }} Flagged)
          </button>
        </form>
        @endif
      @endif
      @if($bill->status === 'matched')
        <form method="POST" action="{{ route('finance.bpjs-reconciliation.reconcile', $bill) }}">
          @csrf
          <button type="submit" class="px-4 py-2 rounded bg-indigo-600 text-white text-sm">
            Jalankan Rekonsiliasi
          </button>
        </form>
      @endif
      @if($bill->status === 'reconciled')
        <a href="{{ route('finance.bpjs-reconciliation.dashboard', $bill) }}"
           class="px-4 py-2 rounded bg-green-600 text-white text-sm">
          Lihat Dashboard
        </a>
      @endif
    </div>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
      @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
    </div>
  @endif

  {{-- Stats cards --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    @php
      $total    = $statusCounts->sum();
      $auto     = $statusCounts->get('auto', 0);
      $manual   = $statusCounts->get('manual_confirmed', 0);
      $flagged  = $statusCounts->get('flagged', 0);
      $notFound = $statusCounts->get('not_found', 0);
    @endphp
    <div class="bg-white rounded-lg border p-4">
      <div class="text-2xl font-bold">{{ number_format($total) }}</div>
      <div class="text-xs text-gray-500 mt-0.5">Total Karyawan</div>
    </div>
    <div class="bg-white rounded-lg border p-4">
      <div class="text-2xl font-bold text-green-600">{{ number_format($auto + $manual) }}</div>
      <div class="text-xs text-gray-500 mt-0.5">Auto + Manual Match</div>
    </div>
    <div class="bg-white rounded-lg border p-4">
      <div class="text-2xl font-bold text-yellow-600">{{ number_format($flagged) }}</div>
      <div class="text-xs text-gray-500 mt-0.5">Perlu Konfirmasi</div>
    </div>
    <div class="bg-white rounded-lg border p-4">
      <div class="text-2xl font-bold text-red-600">{{ number_format($notFound) }}</div>
      <div class="text-xs text-gray-500 mt-0.5">Tidak Ditemukan</div>
    </div>
  </div>

  {{-- Row table --}}
  <div class="bg-white rounded-lg border overflow-hidden">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h2 class="font-medium text-sm">Data Karyawan dari Tagihan</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wide">
          <tr>
            <th class="px-4 py-2.5 text-left">NIK</th>
            <th class="px-4 py-2.5 text-left">Nama (Tagihan)</th>
            <th class="px-4 py-2.5 text-left">Karyawan OMEO</th>
            <th class="px-4 py-2.5 text-right">Total Iuran</th>
            <th class="px-4 py-2.5 text-center">Status</th>
            <th class="px-4 py-2.5 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @foreach($rows as $row)
          <tr class="hover:bg-gray-50 {{ $row->match_status === 'not_found' ? 'bg-red-50/30' : ($row->match_status === 'flagged' ? 'bg-yellow-50/30' : '') }}">
            <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $row->nik ?? '—' }}</td>
            <td class="px-4 py-2.5 font-medium">{{ $row->nama }}</td>
            <td class="px-4 py-2.5">
              @if($row->employee)
                <div class="text-sm">{{ $row->employee->full_name }}</div>
                <div class="text-xs text-gray-400 font-mono">{{ $row->employee->nik }}</div>
              @elseif($row->match_status === 'flagged' && $row->match_note)
                <div class="text-xs text-yellow-700">{{ $row->match_note }}</div>
              @else
                <span class="text-xs text-gray-400">—</span>
              @endif
            </td>
            <td class="px-4 py-2.5 text-right font-mono text-xs">
              {{ number_format((float)$row->total_iuran, 0, ',', '.') }}
            </td>
            <td class="px-4 py-2.5 text-center">
              @php
                $badge = match($row->match_status) {
                  'auto'             => 'bg-green-100 text-green-700',
                  'manual_confirmed' => 'bg-blue-100 text-blue-700',
                  'flagged'          => 'bg-yellow-100 text-yellow-700',
                  'not_found'        => 'bg-red-100 text-red-700',
                  default            => 'bg-gray-100 text-gray-500',
                };
                $labels = ['auto'=>'Auto','manual_confirmed'=>'Manual','flagged'=>'Flagged','not_found'=>'Not Found','pending'=>'Pending'];
              @endphp
              <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                {{ $labels[$row->match_status] ?? $row->match_status }}
              </span>
              @if($row->match_confidence)
                <div class="text-xs text-gray-400 mt-0.5">{{ $row->match_confidence }}%</div>
              @endif
            </td>
            <td class="px-4 py-2.5 text-center">
              @if(in_array($row->match_status, ['flagged', 'not_found']))
                <button type="button"
                        onclick="openConfirmModal({{ $row->id }}, '{{ addslashes($row->nama) }}')"
                        class="text-xs text-indigo-600 hover:underline">
                  Konfirmasi
                </button>
              @elseif($row->match_status === 'auto')
                <span class="text-xs text-gray-400">✓</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{ $rows->links() }}
</div>

{{-- Manual match modal --}}
<div id="confirmModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
  <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
    <h3 class="text-lg font-semibold mb-1">Konfirmasi Manual Match</h3>
    <p class="text-sm text-gray-500 mb-4">Pilih karyawan OMEO yang sesuai dengan <strong id="modalName"></strong></p>

    <form method="POST" id="confirmForm">
      @csrf
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Karyawan OMEO</label>
        <input type="text" id="empSearch" placeholder="Cari nama atau NIK..."
               class="border rounded px-3 py-2 w-full text-sm mb-2"
               oninput="filterEmployees(this.value)">
        <select name="employee_id" id="empSelect" size="6"
                class="border rounded w-full text-sm font-mono">
          {{-- Populated via JS --}}
        </select>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="px-4 py-2 rounded bg-gray-900 text-white text-sm">Konfirmasi</button>
        <button type="button" onclick="closeModal()" class="px-4 py-2 rounded border text-sm">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
const allEmployees = @json(\App\Models\Employee::select('id','full_name','nik')->whereNull('deleted_at')->orderBy('full_name')->get());

function openConfirmModal(rowId, nama) {
  document.getElementById('modalName').textContent = nama;
  document.getElementById('confirmForm').action = '/finance/bpjs-reconciliation/rows/' + rowId + '/confirm';
  populateEmployees('');
  document.getElementById('confirmModal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('confirmModal').classList.add('hidden');
}

function populateEmployees(query) {
  const sel = document.getElementById('empSelect');
  sel.innerHTML = '';
  const q = query.toLowerCase();
  allEmployees
    .filter(e => !q || e.full_name.toLowerCase().includes(q) || (e.nik && e.nik.includes(q)))
    .slice(0, 50)
    .forEach(e => {
      const opt = document.createElement('option');
      opt.value = e.id;
      opt.textContent = `${e.full_name} [${e.nik ?? '—'}]`;
      sel.appendChild(opt);
    });
}

function filterEmployees(q) { populateEmployees(q); }
</script>
@endsection
