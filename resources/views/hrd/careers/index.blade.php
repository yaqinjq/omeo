@extends('layouts.app')

@section('page_title', 'Career Portal')

@section('content')
<div class="space-y-5">
  <div class="card p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Career Portal</h1>
        <p class="mt-1 text-sm text-muted">Kelola lowongan yang tampil di halaman publik /karir.</p>
      </div>
      <a href="{{ route('dashboard.careers.create') }}" class="btn-primary">Tambah Lowongan</a>
    </div>
  </div>

  <div class="card p-4">
    <form method="GET" class="flex flex-wrap items-end gap-3">
      <label class="text-sm font-semibold">Status
        <select name="status" class="mt-1 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950/50">
          <option value="">Semua</option>
          @foreach($statuses as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
          @endforeach
        </select>
      </label>
      <button class="btn" type="submit">Filter</button>
      <a href="{{ route('dashboard.careers.index') }}" class="btn-ghost">Reset</a>
    </form>
  </div>

  <div class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-900">
          <tr>
            <th class="px-4 py-3">Lowongan</th>
            <th class="px-4 py-3">Departemen</th>
            <th class="px-4 py-3">Lokasi</th>
            <th class="px-4 py-3">Tipe</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          @forelse($posts as $post)
            <tr>
              <td class="px-4 py-3">
                <div class="font-semibold">{{ $post->title }}</div>
                <div class="text-xs text-muted">{{ route('careers.show', $post) }}</div>
              </td>
              <td class="px-4 py-3">{{ $post->department?->name ?? '-' }}</td>
              <td class="px-4 py-3">{{ $post->location ?: '-' }}</td>
              <td class="px-4 py-3">{{ $post->employment_type }}</td>
              <td class="px-4 py-3"><span class="badge">{{ ucfirst($post->status) }}</span></td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <a href="{{ route('careers.show', $post) }}" target="_blank" class="btn-ghost">Preview</a>
                  <a href="{{ route('dashboard.careers.edit', $post) }}" class="btn-outline">Edit</a>
                  <form method="POST" action="{{ route('dashboard.careers.destroy', $post) }}" onsubmit="return confirm('Hapus lowongan ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn-danger" type="submit">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-muted">Belum ada lowongan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{ $posts->links() }}
</div>
@endsection
