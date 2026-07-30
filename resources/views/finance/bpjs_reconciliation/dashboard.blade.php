@extends('layouts.app')
@section('content')
<div class="space-y-5">
  {{-- Header --}}
  <div class="flex items-start justify-between flex-wrap gap-3">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <a href="{{ route('finance.bpjs-reconciliation.show', $bill) }}" class="text-sm text-gray-500 hover:text-gray-700">
          ← Detail Tagihan
        </a>
      </div>
      <h1 class="text-2xl font-semibold">Dashboard Rekonsiliasi BPJS</h1>
      <p class="text-sm text-slate-500 mt-0.5">
        {{ $bill->periode_label }} — {{ $bill->legalEntity?->name ?? 'Semua Entitas' }}
        @if($bill->npp) · NPP: <span class="font-mono">{{ $bill->npp }}</span>@endif
      </p>
    </div>
    <a href="{{ route('finance.bpjs-reconciliation.index') }}" class="px-4 py-2 rounded border text-sm">
      ← Semua Tagihan
    </a>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
  @endif

  {{-- Summary cards --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white rounded-lg border p-4">
      <div class="text-2xl font-bold">{{ number_format($summary->total) }}</div>
      <div class="text-xs text-gray-500 mt-0.5">Total Karyawan</div>
    </div>
    <div class="bg-white rounded-lg border border-green-200 p-4">
      <div class="text-2xl font-bold text-green-600">{{ number_format($summary->ok_count) }}</div>
      <div class="text-xs text-gray-500 mt-0.5">Cocok (OK)</div>
    </div>
    <div class="bg-white rounded-lg border border-yellow-200 p-4">
      <div class="text-2xl font-bold text-yellow-600">{{ number_format($summary->minor_count) }}</div>
      <div class="text-xs text-gray-500 mt-0.5">Selisih Minor</div>
    </div>
    <div class="bg-white rounded-lg border border-red-200 p-4">
      <div class="text-2xl font-bold text-red-600">{{ number_format($summary->signifikan_count + $summary->review_count) }}</div>
      <div class="text-xs text-gray-500 mt-0.5">Perlu Perhatian</div>
    </div>
  </div>

  {{-- Totals comparison --}}
  <div class="bg-white rounded-lg border p-5">
    <h2 class="font-semibold text-sm mb-4 border-b pb-2">Perbandingan Total Iuran</h2>
    <div class="grid grid-cols-3 gap-6 text-center">
      <div>
        <div class="text-xs text-gray-500 mb-1">Tagihan Resmi BPJS</div>
        <div class="text-xl font-bold font-mono">
          Rp {{ number_format($summary->total_official, 0, ',', '.') }}
        </div>
      </div>
      <div>
        <div class="text-xs text-gray-500 mb-1">Kalkulasi OMEO</div>
        <div class="text-xl font-bold font-mono">
          Rp {{ number_format($summary->total_omeo, 0, ',', '.') }}
        </div>
      </div>
      <div>
        <div class="text-xs text-gray-500 mb-1">Selisih</div>
        @php $selisih = (float)$summary->total_selisih; @endphp
        <div class="text-xl font-bold font-mono {{ $selisih > 0 ? 'text-red-600' : ($selisih < 0 ? 'text-blue-600' : 'text-green-600') }}">
          {{ $selisih >= 0 ? '+' : '' }}Rp {{ number_format($selisih, 0, ',', '.') }}
        </div>
        <div class="text-xs text-gray-400 mt-0.5">
          {{ $selisih > 0 ? 'Tagihan > OMEO' : ($selisih < 0 ? 'Tagihan < OMEO' : 'Sama persis') }}
        </div>
      </div>
    </div>
  </div>

  {{-- Detail table --}}
  <div class="bg-white rounded-lg border overflow-hidden">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h2 class="font-medium text-sm">Detail Rekonsiliasi per Karyawan</h2>
      <div class="flex gap-2 text-xs">
        @php
          $statusColors = ['ok'=>'green','selisih_minor'=>'yellow','selisih_signifikan'=>'red','perlu_review'=>'purple'];
          $statusLabels = ['ok'=>'OK','selisih_minor'=>'Minor','selisih_signifikan'=>'Signifikan','perlu_review'=>'Review'];
        @endphp
        @foreach($statusColors as $s => $c)
          <span class="px-2 py-0.5 rounded-full bg-{{ $c }}-100 text-{{ $c }}-700 font-medium">{{ $statusLabels[$s] }}</span>
        @endforeach
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wide">
          <tr>
            <th class="px-4 py-2.5 text-left">Karyawan</th>
            <th class="px-4 py-2.5 text-right">Tagihan BPJS</th>
            <th class="px-4 py-2.5 text-right">OMEO</th>
            <th class="px-4 py-2.5 text-right">Selisih</th>
            <th class="px-4 py-2.5 text-center">Penyebab</th>
            <th class="px-4 py-2.5 text-center">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($results as $res)
          @php
            $selisih = (float)$res->selisih_total;
            $penyebabLabels = [
              'ok' => 'OK',
              'tidak_terdaftar_omeo' => 'Tidak di OMEO',
              'tidak_di_tagihan' => 'Tidak di Tagihan',
              'beda_gaji' => 'Beda Gaji',
              'beda_tarif' => 'Beda Tarif',
              'karyawan_baru' => 'Karyawan Baru',
              'karyawan_resign' => 'Sudah Resign',
              'lainnya' => 'Lainnya',
            ];
            $statusBadge = match($res->status) {
              'ok' => 'bg-green-100 text-green-700',
              'selisih_minor' => 'bg-yellow-100 text-yellow-700',
              'selisih_signifikan' => 'bg-red-100 text-red-700',
              'perlu_review' => 'bg-purple-100 text-purple-700',
              default => 'bg-gray-100 text-gray-500',
            };
          @endphp
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2.5">
              <div class="font-medium">{{ $res->employee?->full_name ?? $res->billRow?->nama ?? '—' }}</div>
              <div class="text-xs text-gray-400 font-mono">{{ $res->employee?->nik ?? '—' }}</div>
            </td>
            <td class="px-4 py-2.5 text-right font-mono text-xs">
              {{ number_format((float)$res->official_total, 0, ',', '.') }}
            </td>
            <td class="px-4 py-2.5 text-right font-mono text-xs">
              {{ number_format((float)$res->omeo_total, 0, ',', '.') }}
            </td>
            <td class="px-4 py-2.5 text-right font-mono text-xs font-semibold
                       {{ $selisih > 0 ? 'text-red-600' : ($selisih < 0 ? 'text-blue-600' : 'text-green-600') }}">
              {{ $selisih >= 0 ? '+' : '' }}{{ number_format($selisih, 0, ',', '.') }}
            </td>
            <td class="px-4 py-2.5 text-center text-xs text-gray-600">
              {{ $penyebabLabels[$res->penyebab] ?? $res->penyebab }}
            </td>
            <td class="px-4 py-2.5 text-center">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                {{ $statusLabels[$res->status] ?? $res->status }}
              </span>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada hasil rekonsiliasi.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $results->links() }}
</div>
@endsection
