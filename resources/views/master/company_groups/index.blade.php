@extends('layouts.app')
@section('title', 'Group Perusahaan')

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Group Perusahaan</h1>
            <p class="text-sm text-gray-500 mt-0.5">Holding dan group usaha induk</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Tombol Import --}}
            <a href="{{ route('master.company-groups.import.form') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium
                      text-indigo-600 bg-indigo-50 border border-indigo-200
                      rounded-lg hover:bg-indigo-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import Excel
            </a>
            {{-- Tombol Tambah --}}
            <a href="{{ route('master.company-groups.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium
                      text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Group
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul>@foreach($errors->all() as $e)<li>• {{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama Group</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Singkatan</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">NPWP</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kota</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">PT/CV</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($groups as $group)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3">
                        <p class="text-sm font-semibold text-gray-900">{{ $group->name }}</p>
                        @if($group->website)
                            <p class="text-xs text-gray-400 mt-0.5">{{ $group->website }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">
                        {{ $group->short_name ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-sm font-mono text-gray-600">
                        {{ $group->npwp ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-600">
                        {{ $group->headquarters_city ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-block px-2 py-0.5 text-xs bg-indigo-100
                                     text-indigo-700 rounded-full font-medium">
                            {{ $group->legal_entities_count ?? 0 }} entitas
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <form action="{{ route('master.company-groups.toggle', $group) }}"
                              method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    title="{{ $group->is_active ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan' }}"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full
                                           transition-colors focus:outline-none
                                           {{ $group->is_active ? 'bg-green-500' : 'bg-red-400' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow
                                             transition-transform
                                             {{ $group->is_active ? 'translate-x-6' : 'translate-x-1' }}">
                                </span>
                            </button>
                        </form>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex justify-end gap-3">
                            <a href="{{ route('master.company-groups.edit', $group) }}"
                               class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                Edit
                            </a>
                            <form action="{{ route('master.company-groups.destroy', $group) }}"
                                  method="POST"
                                  onsubmit="return confirm('Hapus group \'{{ addslashes($group->name) }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="text-sm text-red-500 hover:text-red-700 font-medium">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-gray-400 text-sm">
                        Belum ada data group perusahaan.
                        <a href="{{ route('master.company-groups.create') }}"
                           class="text-indigo-600 hover:underline ml-1">Tambah sekarang →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $groups->links() }}</div>
</div>
@endsection
