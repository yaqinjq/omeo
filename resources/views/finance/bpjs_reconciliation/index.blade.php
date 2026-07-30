@extends('layouts.app')
@section('content')
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Rekonsiliasi BPJS</h1>
      <p class="text-sm text-slate-500 mt-0.5">Upload & bandingkan tagihan resmi Formulir 2a PU</p>
    </div>
    @can('finance.import')
    <a href="{{ route('finance.bpjs-reconciliation.create') }}"
       class="px-4 py-2 rounded bg-gray-900 text-white text-sm flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      Upload Tagihan
    </a>
    @endcan
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
  @endif

  {{-- Filter --}}
  <form method="GET" class="flex gap-3 flex-wrap items-end">
    <div>
      <label class="block text-xs font-medium mb-1">Periode</label>
      <input type="text" name="periode" value="{{ request('periode') }}"
             placeholder="2025-01" pattern="\d{4}-\d{2}"
             class="border rounded px-3 py-1.5 text-sm w-32 font-mono">
    </div>
    <div>
      <label class="block text-xs font-medium mb-1">Status</label>
      <select name="status" class="border rounded px-3 py-1.5 text-sm">
        <option value="">Semua</option>
        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
        <option value="matched" @selected(request('status') === 'matched')>Matched</option>
        <option value="reconciled" @selected(request('status') === 'reconciled')>Reconciled</option>
      </select>
    </div>
    <button type="submit" class="px-4 py-1.5 rounded border text-sm">Filter</button>
    @if(request()->hasAny(['periode','status']))
      <a href="{{ route('finance.bpjs-reconciliation.index') }}" class="px-4 py-1.5 rounded border text-sm text-gray-500">Reset</a>
    @endif
  </form>

  {{-- Table --}}
  <div class="bg-white rounded-lg border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wide">
        <tr>
          <th class="px-4 py-3 text-left">Periode</th>
          <th class="px-4 py-3 text-left">Entitas / NPP</th>
          <th class="px-4 py-3 text-left">File</th>
          <th class="px-4 py-3 text-right">Karyawan</th>
          <th class="px-4 py-3 text-right">Total Iuran Resmi</th>
          <th class="px-4 py-3 text-center">Status</th>
          <th class="px-4 py-3 text-center">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        @forelse($bills as $bill)
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-3 font-mono font-medium">{{ $bill->periode_label }}</td>
          <td class="px-4 py-3">
            <div>{{ $bill->legalEntity?->short_name ?? $bill->legalEntity?->name ?? '—' }}</div>
            @if($bill->npp)<div class="text-xs text-gray-400 font-mono">NPP: {{ $bill->npp }}</div>@endif
          </td>
          <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
            {{ $bill->source_file_name }}
            <span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600 uppercase">{{ $bill->source_format }}</span>
          </td>
          <td class="px-4 py-3 text-right">
            <div>{{ number_format($bill->total_rows_parsed) }}</div>
            <div class="text-xs text-gray-400">
              <span class="text-green-600">{{ $bill->matched_count }} matched</span>
              @if($bill->unmatched_count > 0)
                · <span class="text-red-500">{{ $bill->unmatched_count }} unmatched</span>
              @endif
            </div>
          </td>
          <td class="px-4 py-3 text-right font-mono">
            Rp {{ number_format($bill->total_iuran_official, 0, ',', '.') }}
          </td>
          <td class="px-4 py-3 text-center">
            @php
              $badge = match($bill->status) {
                'draft'       => 'bg-gray-100 text-gray-600',
                'matched'     => 'bg-blue-100 text-blue-700',
                'reconciled'  => 'bg-green-100 text-green-700',
                default       => 'bg-gray-100 text-gray-500',
              };
            @endphp
            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
              {{ ucfirst($bill->status) }}
            </span>
          </td>
          <td class="px-4 py-3 text-center">
            <div class="flex justify-center gap-2 flex-wrap">
              <a href="{{ route('finance.bpjs-reconciliation.show', $bill) }}"
                 class="text-xs text-indigo-600 hover:underline">Detail</a>
              @if($bill->status === 'matched' || $bill->status === 'reconciled')
                <a href="{{ route('finance.bpjs-reconciliation.dashboard', $bill) }}"
                   class="text-xs text-green-600 hover:underline">Dashboard</a>
              @endif
              @can('finance.import')
              <form method="POST"
                    action="{{ route('finance.bpjs-reconciliation.destroy', $bill->id) }}"
                    onsubmit="return confirm('Yakin hapus tagihan ini? Semua data match akan ikut terhapus.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Hapus</button>
              </form>
              @endcan
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="px-4 py-8 text-center text-gray-400">
            Belum ada tagihan diupload.
            <a href="{{ route('finance.bpjs-reconciliation.create') }}" class="text-indigo-600 hover:underline">Upload sekarang</a>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{ $bills->links() }}
</div>
@endsection
