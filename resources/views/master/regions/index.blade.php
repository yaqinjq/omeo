@extends('layouts.app')
@section('content')
<div class="max-w-5xl mx-auto space-y-4">
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Master Regional & UMR</h1>
      <p class="text-sm text-slate-500">Kelola wilayah operasional beserta data UMR dan kode area BPJS per regional.</p>
    </div>
    <a href="{{ route('master.regions.create') }}" class="px-4 py-2 rounded bg-gray-900 text-white text-sm">+ Tambah Regional</a>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
  @endif

  <div class="bg-white rounded-lg border overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-left">
          <th class="p-3">Nama Regional</th>
          <th class="p-3">Provinsi</th>
          <th class="p-3">Entitas Legal</th>
          <th class="p-3 text-right">UMR</th>
          <th class="p-3">Thn</th>
          <th class="p-3">Kode BPJS</th>
          <th class="p-3 text-center">Status</th>
          <th class="p-3 w-40">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $it)
          <tr class="border-t hover:bg-slate-50">
            <td class="p-3 font-medium">{{ $it->name }}</td>
            <td class="p-3 text-slate-500">{{ $it->province ?? '-' }}</td>
            <td class="p-3 text-xs text-slate-500">{{ $it->legalEntity?->short_name ?? $it->legalEntity?->name ?? '-' }}</td>
            <td class="p-3 text-right font-mono text-xs">
              {{ $it->umr_amount ? 'Rp ' . number_format($it->umr_amount, 0, ',', '.') : '-' }}
            </td>
            <td class="p-3 text-center text-xs">{{ $it->umr_year ?? '-' }}</td>
            <td class="p-3 font-mono text-xs">{{ $it->bpjs_area_code ?? '-' }}</td>
            <td class="p-3 text-center">
              <span class="px-2 py-0.5 rounded text-xs {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $it->is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="p-3">
              <a class="text-indigo-600 text-xs" href="{{ route('master.regions.edit', $it) }}">Edit</a>
              <form method="POST" action="{{ route('master.regions.toggle', $it) }}" class="inline">
                @csrf @method('PATCH')
                <button class="text-xs ml-3 {{ $it->is_active ? 'text-amber-600' : 'text-green-600' }}">
                  {{ $it->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
              </form>
              <form method="POST" action="{{ route('master.regions.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus regional ini?')">
                @csrf @method('DELETE')
                <button class="text-red-600 ml-3 text-xs">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="p-6 text-center text-slate-400">Belum ada data regional.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div>{{ $items->links() }}</div>
</div>
@endsection
