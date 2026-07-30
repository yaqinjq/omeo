@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl p-4 sm:p-6">
  <div class="rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="border-b px-6 py-4 dark:border-gray-700">
      <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Roles & Permissions</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Kelola role dinamis dan mapping permission.</p>
    </div>

    <div class="space-y-5 p-6">
      @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
      @endif

      @if($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          <ul class="list-disc pl-5">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('hrd.roles.index') }}" class="flex w-full gap-2 sm:w-auto">
          <input type="text" name="q" value="{{ $search }}" placeholder="Cari role..." class="w-full rounded-lg border-gray-300 text-sm sm:w-80">
          <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Cari</button>
        </form>
        <a href="{{ route('hrd.roles.create') }}" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Buat Role</a>
      </div>

      <div class="overflow-x-auto rounded-xl border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left">Role</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Permission</th>
              <th class="px-4 py-3 text-left">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @forelse($roles as $role)
              <tr>
                <td class="px-4 py-3">
                  <div class="font-semibold text-gray-900">{{ $role->name }}</div>
                  <div class="text-xs text-gray-500">{{ $role->slug }}</div>
                </td>
                <td class="px-4 py-3">
                  @if($role->is_system)
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">system role</span>
                  @endif
                  @if($role->is_super_admin)
                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">super admin</span>
                  @endif
                </td>
                <td class="px-4 py-3">{{ $role->permissions_count }}</td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-2">
                    <a href="{{ route('hrd.roles.edit', $role) }}" class="rounded-lg bg-slate-700 px-3 py-1 text-xs font-semibold text-white">Edit</a>
                    <a href="{{ route('hrd.roles.permissions', $role) }}" class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white">Permissions</a>
                    @if(!$role->is_system && !$role->is_super_admin)
                      <form method="POST" action="{{ route('hrd.roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1 text-xs font-semibold text-white">Delete</button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Role tidak ditemukan.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{ $roles->links() }}
    </div>
  </div>
</div>
@endsection
