@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto space-y-4">
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Konfigurasi Tarif BPJS</h1>
      <p class="text-sm text-slate-500">Kelola persentase iuran BPJS Ketenagakerjaan & Kesehatan per regional dan periode.</p>
    </div>
    <a href="{{ route('finance.bpjs-rates.create') }}" class="px-4 py-2 rounded bg-gray-900 text-white text-sm">+ Tambah Konfigurasi</a>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
  @endif

  <div class="bg-white rounded-lg border overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-left">
          <th class="p-3">Regional</th>
          <th class="p-3">Berlaku Mulai</th>
          <th class="p-3">Berlaku s/d</th>
          <th class="p-3 text-right">JHT P</th>
          <th class="p-3 text-right">JHT K</th>
          <th class="p-3 text-right">JP P</th>
          <th class="p-3 text-right">JP K</th>
          <th class="p-3 text-right">Kes P</th>
          <th class="p-3 text-right">Kes K</th>
          <th class="p-3 text-center">Status</th>
          <th class="p-3 w-32">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $it)
          <tr class="border-t hover:bg-slate-50">
            <td class="p-3">
              <div class="font-medium">{{ $it->region?->name ?? 'Semua Regional' }}</div>
              @if($it->region?->legalEntity)<div class="text-xs text-slate-400">{{ $it->region->legalEntity->short_name ?? $it->region->legalEntity->name }}</div>@endif
            </td>
            <td class="p-3 font-mono text-xs">{{ $it->periode_mulai }}</td>
            <td class="p-3 font-mono text-xs">{{ $it->periode_selesai ?? 'Sekarang' }}</td>
            <td class="p-3 text-right font-mono text-xs">{{ ($it->jht_employer_rate * 100) }}%</td>
            <td class="p-3 text-right font-mono text-xs">{{ ($it->jht_employee_rate * 100) }}%</td>
            <td class="p-3 text-right font-mono text-xs">{{ ($it->jp_employer_rate * 100) }}%</td>
            <td class="p-3 text-right font-mono text-xs">{{ ($it->jp_employee_rate * 100) }}%</td>
            <td class="p-3 text-right font-mono text-xs">{{ ($it->bpjskes_employer_rate * 100) }}%</td>
            <td class="p-3 text-right font-mono text-xs">{{ ($it->bpjskes_employee_rate * 100) }}%</td>
            <td class="p-3 text-center">
              <span class="px-2 py-0.5 rounded text-xs {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $it->is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="p-3">
              <a class="text-indigo-600 text-xs" href="{{ route('finance.bpjs-rates.edit', $it) }}">Edit</a>
              <form method="POST" action="{{ route('finance.bpjs-rates.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus konfigurasi ini?')">
                @csrf @method('DELETE')
                <button class="text-red-600 ml-3 text-xs">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="11" class="p-6 text-center text-slate-400">Belum ada konfigurasi tarif BPJS.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="text-xs text-slate-400">P = Perusahaan, K = Karyawan</div>
  <div>{{ $items->links() }}</div>
</div>
@endsection
