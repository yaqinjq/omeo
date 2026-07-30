@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto space-y-4">
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Master Entitas Legal</h1>
      <p class="text-sm text-slate-500">Kelola PT/CV/UD yang terdaftar sebagai pemberi kerja (employer) untuk keperluan BPJS dan kontrak.</p>
    </div>
    <a href="{{ route('master.legal-entities.create') }}" class="px-4 py-2 rounded bg-gray-900 text-white text-sm">+ Tambah Entitas</a>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
  @endif

  <div class="bg-white rounded-lg border overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-left">
          <th class="p-3">Nama Entitas</th>
          <th class="p-3">Tipe</th>
          <th class="p-3">Group</th>
          <th class="p-3">NPWP</th>
          <th class="p-3">Kota</th>
          <th class="p-3">Direktur</th>
          <th class="p-3 text-center">Status</th>
          <th class="p-3 w-40">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $it)
          <tr class="border-t hover:bg-slate-50">
            <td class="p-3">
              <div class="font-medium">{{ $it->name }}</div>
              @if($it->short_name)<div class="text-xs text-slate-400">{{ $it->short_name }}</div>@endif
            </td>
            <td class="p-3">
              <span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700">{{ $it->entity_type }}</span>
            </td>
            <td class="p-3 text-slate-500 text-xs">{{ $it->companyGroup?->short_name ?? $it->companyGroup?->name ?? '-' }}</td>
            <td class="p-3 font-mono text-xs">{{ $it->npwp ?? '-' }}</td>
            <td class="p-3 text-slate-500 text-xs">{{ $it->city ?? '-' }}</td>
            <td class="p-3 text-xs">{{ $it->director_name ?? '-' }}</td>
            <td class="p-3 text-center">
              <span class="px-2 py-0.5 rounded text-xs {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $it->is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="p-3">
              <a class="text-indigo-600 text-xs" href="{{ route('master.legal-entities.edit', $it) }}">Edit</a>
              <form method="POST" action="{{ route('master.legal-entities.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus entitas ini?')">
                @csrf @method('DELETE')
                <button class="text-red-600 ml-3 text-xs">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="p-6 text-center text-slate-400">Belum ada entitas legal.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div>{{ $items->links() }}</div>
</div>
@endsection
