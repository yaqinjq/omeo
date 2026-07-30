@extends('layouts.app')
@section('title', 'Mapping Departemen')
@section('content')
<div class="max-w-4xl mx-auto space-y-4">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Mapping Departemen → Kategori</h1>
      <p class="text-sm text-slate-500 mt-0.5">
        Petakan Sub Dept atau Posisi ke kategori ringkasan (bar, kitchen, service, office, prive).
      </p>
    </div>
    <a href="{{ route('finance.department-mappings.create') }}"
       class="px-4 py-2 rounded bg-gray-900 text-white text-sm">+ Tambah</a>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
  @endif

  <div class="bg-white rounded-lg border overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
          <th class="p-3">Tipe</th>
          <th class="p-3">Nilai</th>
          <th class="p-3">Kategori</th>
          <th class="p-3 text-center">Status</th>
          <th class="p-3 w-48">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $it)
          @php
            $catColors = [
              'bar'     => 'bg-purple-100 text-purple-700',
              'kitchen' => 'bg-orange-100 text-orange-700',
              'service' => 'bg-blue-100 text-blue-700',
              'office'  => 'bg-gray-100 text-gray-700',
              'prive'   => 'bg-yellow-100 text-yellow-700',
              'lainnya' => 'bg-slate-100 text-slate-500',
            ];
          @endphp
          <tr class="border-t hover:bg-slate-50">
            <td class="p-3">
              <span class="px-2 py-0.5 rounded text-xs font-medium
                {{ $it->match_type === 'position' ? 'bg-indigo-100 text-indigo-700' : 'bg-teal-100 text-teal-700' }}">
                {{ $it->match_type === 'position' ? 'Posisi' : 'Sub Dept' }}
              </span>
            </td>
            <td class="p-3 font-mono text-xs">{{ $it->match_value }}</td>
            <td class="p-3">
              <span class="px-2 py-0.5 rounded text-xs font-medium {{ $catColors[$it->category] ?? 'bg-gray-100 text-gray-500' }}">
                {{ strtoupper($it->category) }}
              </span>
            </td>
            <td class="p-3 text-center">
              <span class="px-2 py-0.5 rounded text-xs {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $it->is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="p-3 space-x-2">
              <a class="text-indigo-600 text-xs" href="{{ route('finance.department-mappings.edit', $it) }}">Edit</a>
              <form method="POST" action="{{ route('finance.department-mappings.toggle', $it) }}" class="inline">
                @csrf @method('PATCH')
                <button class="text-xs {{ $it->is_active ? 'text-amber-600' : 'text-green-600' }}">
                  {{ $it->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
              </form>
              <form method="POST" action="{{ route('finance.department-mappings.destroy', $it) }}"
                    class="inline" onsubmit="return confirm('Hapus mapping ini?')">
                @csrf @method('DELETE')
                <button class="text-red-600 text-xs">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="p-8 text-center text-slate-400">Belum ada mapping. Klik "+ Tambah" atau jalankan seeder.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>{{ $items->links() }}</div>
</div>
@endsection
