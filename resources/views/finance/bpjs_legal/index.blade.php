@extends('layouts.app')
@section('content')
<div class="max-w-5xl mx-auto space-y-4">
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Akun BPJS per Entitas Legal</h1>
      <p class="text-sm text-slate-500">Kelola nomor kepesertaan BPJS Ketenagakerjaan dan Kesehatan untuk setiap entitas legal (PT/CV).</p>
    </div>
    <a href="{{ route('finance.bpjs-legal.create') }}" class="px-4 py-2 rounded bg-gray-900 text-white text-sm">+ Tambah Akun</a>
  </div>

  @if(session('success'))
    <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
  @endif

  <div class="bg-white rounded-lg border overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-left">
          <th class="p-3">Entitas Legal</th>
          <th class="p-3">Tipe BPJS</th>
          <th class="p-3">No. Kepesertaan</th>
          <th class="p-3">Nama Peserta</th>
          <th class="p-3">Kantor Cabang</th>
          <th class="p-3">Tgl Daftar</th>
          <th class="p-3 text-center">Status</th>
          <th class="p-3 w-32">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $it)
          <tr class="border-t hover:bg-slate-50">
            <td class="p-3 font-medium">{{ $it->legalEntity?->short_name ?? $it->legalEntity?->name ?? '-' }}</td>
            <td class="p-3">
              <span class="px-2 py-0.5 rounded text-xs {{ $it->bpjs_type === 'ketenagakerjaan' ? 'bg-blue-100 text-blue-700' : 'bg-teal-100 text-teal-700' }}">
                {{ $it->bpjs_type === 'ketenagakerjaan' ? 'Ketenagakerjaan' : 'Kesehatan' }}
              </span>
            </td>
            <td class="p-3 font-mono text-xs">{{ $it->account_number }}</td>
            <td class="p-3 text-xs">{{ $it->account_name ?? '-' }}</td>
            <td class="p-3 text-xs text-slate-500">{{ $it->bpjs_branch ?? '-' }}</td>
            <td class="p-3 text-xs">{{ $it->registered_date?->format('d/m/Y') ?? '-' }}</td>
            <td class="p-3 text-center">
              <span class="px-2 py-0.5 rounded text-xs {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $it->is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="p-3">
              <a class="text-indigo-600 text-xs" href="{{ route('finance.bpjs-legal.edit', $it) }}">Edit</a>
              <form method="POST" action="{{ route('finance.bpjs-legal.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus akun ini?')">
                @csrf @method('DELETE')
                <button class="text-red-600 ml-3 text-xs">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="p-6 text-center text-slate-400">Belum ada akun BPJS legal.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div>{{ $items->links() }}</div>
</div>
@endsection
