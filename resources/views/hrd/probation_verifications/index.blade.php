@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="card p-6">
        <h1 class="text-xl font-bold">Verifikasi Data Karyawan</h1>
        <p class="text-sm text-muted mt-1">Inbox perubahan data payroll dari karyawan probation.</p>
    </div>

    <div class="card p-6">
        @if(!empty($moduleWarning))
            <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ $moduleWarning }}</div>
        @endif

        <form method="GET" class="grid grid-cols-1 md:flex md:items-end gap-3">
            <div>
                <label class="block text-xs uppercase text-muted">Status</label>
                <select name="status" class="mt-1 rounded-lg border px-3 py-2 w-full md:w-auto">
                    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn">Filter</button>
        </form>

        <div class="mt-4 md:hidden space-y-3">
            @forelse($items as $item)
                @php($changes = $item->changes_json ?? [])
                <div class="rounded-xl border border-white/10 p-4 space-y-2">
                    <div class="font-semibold">{{ $item->user->name ?? '-' }}</div>
                    <div class="text-xs text-muted">{{ optional($item->submitted_at)->format('d-m-Y H:i') ?? '-' }}</div>
                    <div class="text-xs text-muted">{{ implode(', ', array_keys($changes)) ?: '-' }}</div>
                    <div><span class="badge">{{ strtoupper($item->status) }}</span></div>
                    <details class="relative">
                        <summary class="list-none inline-flex cursor-pointer items-center rounded-md border border-white/20 px-3 py-1.5 text-xs font-semibold">Aksi</summary>
                        <div class="absolute right-0 z-20 mt-2 w-40 rounded-lg border border-white/10 bg-slate-900 p-2 shadow-lg">
                            <a href="{{ route('hrd.probation-verifications.show', $item->id) }}" class="block rounded px-3 py-2 text-xs hover:bg-white/10">Review</a>
                        </div>
                    </details>
                </div>
            @empty
                <div class="p-4 text-center text-muted">Tidak ada request.</div>
            @endforelse
        </div>

        <div class="mt-4 overflow-x-auto hidden md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-left">
                        <th class="p-2">Nama</th>
                        <th class="p-2">Submit</th>
                        <th class="p-2">Ringkasan Perubahan</th>
                        <th class="p-2">Status</th>
                        <th class="p-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        @php($changes = $item->changes_json ?? [])
                        <tr class="border-b border-white/5">
                            <td class="p-2">{{ $item->user->name ?? '-' }}</td>
                            <td class="p-2">{{ optional($item->submitted_at)->format('d-m-Y H:i') ?? '-' }}</td>
                            <td class="p-2 text-xs text-muted">{{ implode(', ', array_keys($changes)) ?: '-' }}</td>
                            <td class="p-2"><span class="badge">{{ strtoupper($item->status) }}</span></td>
                            <td class="p-2">
                                <details class="relative inline-block">
                                    <summary class="list-none inline-flex cursor-pointer items-center rounded-md border border-white/20 px-3 py-1.5 text-xs font-semibold">Aksi</summary>
                                    <div class="absolute right-0 z-20 mt-2 w-40 rounded-lg border border-white/10 bg-slate-900 p-2 shadow-lg">
                                        <a href="{{ route('hrd.probation-verifications.show', $item->id) }}" class="block rounded px-3 py-2 text-xs hover:bg-white/10">Review</a>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-center text-muted">Tidak ada request.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>
</div>
@endsection
