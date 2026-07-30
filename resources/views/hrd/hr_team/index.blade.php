@extends('layouts.app')

@section('page_title', 'Tim HR Landing')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Tim Human Resource</h1>
        <p class="mt-1 text-sm text-muted">Data manual untuk section Tim HR di landing page.</p>
      </div>
      <a href="{{ route('dashboard.hr-team.create') }}" class="btn-primary">Tambah Anggota</a>
    </div>
  </div>

  <div class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-900">
          <tr>
            <th class="px-4 py-3">Nama</th>
            <th class="px-4 py-3">Jabatan</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Urutan</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          @forelse($members as $member)
            <tr>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  @if($member->photo_url)
                    <img src="{{ $member->photo_url }}" class="h-10 w-10 rounded-full object-cover" alt="{{ $member->name }}">
                  @else
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-brand/20 font-bold text-brand">{{ substr($member->name, 0, 1) }}</span>
                  @endif
                  <span class="font-semibold">{{ $member->name }}</span>
                </div>
              </td>
              <td class="px-4 py-3">{{ $member->position }}</td>
              <td class="px-4 py-3">{{ $member->company_email }}</td>
              <td class="px-4 py-3">{{ $member->sort_order }}</td>
              <td class="px-4 py-3">
                <span class="badge">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <a href="{{ route('dashboard.hr-team.edit', $member) }}" class="btn-outline">Edit</a>
                  <form method="POST" action="{{ route('dashboard.hr-team.destroy', $member) }}" onsubmit="return confirm('Hapus anggota Tim HR ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn-danger" type="submit">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-muted">Belum ada anggota Tim HR.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $members->links() }}
</div>
@endsection
