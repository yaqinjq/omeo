@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl p-4 sm:p-6">
  <div class="rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="border-b px-6 py-4 dark:border-gray-700">
      <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Manajemen Role User</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Atur role akses user untuk fondasi SaaS (khusus admin/HRD).</p>
    </div>

    <div class="p-6">
      <form method="GET" action="{{ route('hrd.settings.user-roles.index') }}" class="mb-5 grid gap-3 sm:grid-cols-[1fr_auto]">
        <input
          type="text"
          name="q"
          value="{{ $search }}"
          placeholder="Cari nama atau email..."
          class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
        >
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
          Cari
        </button>
      </form>

      <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-900/30">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">User</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Status</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Role Saat Ini</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Ubah Role</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($users as $user)
              @php
                $currentRole = strtolower((string) ($user->role ?? 'employee'));
                $canManageAdmin = strtolower((string) (auth()->user()->role ?? '')) === 'admin';
                $adminRelated = $currentRole === 'admin';
              @endphp
              <tr class="bg-white dark:bg-gray-800/40">
                <td class="px-4 py-3 align-top">
                  <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                  <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">ID: {{ $user->id }}</div>
                </td>
                <td class="px-4 py-3 align-top">
                  @if($user->employee_id)
                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Employee Aktif</span>
                  @else
                    <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Applicant / Non Employee</span>
                  @endif
                </td>
                <td class="px-4 py-3 align-top">
                  <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                    {{ $roleLabels[$currentRole] ?? strtoupper($currentRole) }}
                  </span>
                  @if((int) auth()->id() === (int) $user->id)
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Akun Anda sendiri</div>
                  @endif
                </td>
                <td class="px-4 py-3 align-top">
                  <form method="POST" action="{{ route('hrd.settings.user-roles.update', $user) }}" class="space-y-2">
                    @csrf
                    @method('PUT')
                    <select
                      name="role"
                      class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                      @disabled(!$canManageAdmin && $adminRelated)
                    >
                      @foreach($roleLabels as $roleValue => $roleLabel)
                        <option value="{{ $roleValue }}" @selected($roleValue === $currentRole) @disabled(!$canManageAdmin && $roleValue === 'admin')>
                          {{ $roleLabel }}
                        </option>
                      @endforeach
                    </select>
                    <button
                      type="submit"
                      class="inline-flex items-center rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                      @disabled(!$canManageAdmin && $adminRelated)
                    >
                      Simpan Role
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Data user tidak ditemukan.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-4">
        {{ $users->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
