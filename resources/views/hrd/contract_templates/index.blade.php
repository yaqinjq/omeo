@extends('layouts.app')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Template Kontrak (Daily Worker)</h1>
            <p class="text-sm text-slate-600">Kelola template kontrak untuk proses kandidat lolos.</p>
        </div>
        <a href="{{ route('hrd.contract-templates.create') }}" class="px-4 py-2 rounded bg-slate-900 text-white">+ Template Baru</a>
    </div>

    <form method="GET" class="bg-white border rounded-lg p-4 flex flex-col md:flex-row gap-3 md:items-center">
        <input type="text" name="search" value="{{ $search }}" class="w-full md:max-w-md border rounded px-3 py-2" placeholder="Cari nama template">
        <button class="px-4 py-2 rounded bg-slate-900 text-white">Cari</button>
        @if($search !== '')
            <a href="{{ route('hrd.contract-templates.index') }}" class="px-4 py-2 rounded border">Reset</a>
        @endif
    </form>

    <div class="bg-white border rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="p-3 text-left">Nama</th>
                    <th class="p-3 text-left">Tipe</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Logo</th>
                    <th class="p-3 text-left">Updated At</th>
                    <th class="p-3 text-left">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $template)
                    <tr class="border-t align-top">
                        <td class="p-3 font-medium">{{ $template->name }}</td>
                        <td class="p-3">{{ $template->type }}</td>
                        <td class="p-3">
                            @if($template->is_active)
                                <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-600">Nonactive</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if($template->logo_path)
                                <img src="{{ asset('storage/'.$template->logo_path) }}" alt="logo" class="h-10 border rounded p-1 bg-white">
                            @else
                                -
                            @endif
                        </td>
                        <td class="p-3">{{ $template->updated_at?->format('d/m/Y H:i') ?: '-' }}</td>
                        <td class="p-3 space-y-1">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('hrd.contract-templates.edit', $template) }}" class="underline">Edit</a>

                                @if($template->is_active)
                                    <form method="POST" action="{{ route('hrd.contract-templates.deactivate', $template) }}">
                                        @csrf
                                        <button class="underline text-amber-700" type="submit">Deactivate</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('hrd.contract-templates.activate', $template) }}">
                                        @csrf
                                        <button class="underline text-green-700" type="submit">Activate</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('hrd.contract-templates.destroy', $template) }}" onsubmit="return confirm('Hapus template ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="underline text-red-700" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-slate-500">Belum ada template kontrak.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $templates->links() }}</div>
</div>
@endsection
