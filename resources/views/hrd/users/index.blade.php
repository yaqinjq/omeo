@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl p-4 sm:p-6">
  <div class="rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="border-b px-6 py-4 dark:border-gray-700">
      <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Users & Roles</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Kelola role user menggunakan role dinamis.</p>
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
        <form method="GET" action="{{ route('hrd.users.index') }}" class="flex w-full gap-2 sm:w-auto">
          <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama, email, atau NIK..." class="w-full rounded-lg border-gray-300 text-sm sm:w-96">
          <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Cari</button>
        </form>
        <a href="{{ route('hrd.roles.index') }}" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Roles & Permissions</a>
      </div>

      <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-900/30">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Nama</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Email / NIK</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Role Saat Ini</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Ubah Role</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($users as $user)
              @php
                $currentRole = $user->normalizedRole();
                $currentNik = data_get($user->applicantProfile?->personal_json, 'ktp_number')
                  ?? data_get($user->applicantProfile?->personal_json, 'nik');
              @endphp
              <tr class="bg-white dark:bg-gray-800/40">
                <td class="px-4 py-3 align-top">
                  <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->created_at?->format('d M Y H:i') ?? '-' }}</div>
                </td>
                <td class="px-4 py-3 align-top">
                  <div class="text-gray-800 dark:text-gray-200">{{ $user->email }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">NIK: {{ $currentNik ?: '-' }}</div>
                  @if($canManageRoles)
                    <details class="mt-1.5">
                      <summary class="cursor-pointer text-xs font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400">✏️ Ubah email login</summary>
                      <form method="POST" action="{{ route('hrd.users.email.update', $user) }}" class="mt-2 flex flex-col gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="email" name="email" required value="{{ old('email', $user->email) }}"
                               class="w-full rounded-lg border-gray-300 text-xs focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        <button type="submit" class="inline-flex items-center justify-center self-start rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                          Simpan Email
                        </button>
                      </form>
                    </details>
                  @endif
                </td>
                <td class="px-4 py-3 align-top">
                  <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                    {{ $roleLabels[$currentRole] ?? ucfirst($currentRole) }}
                  </span>
                  @if($user->isSuperAdmin())
                    <div class="mt-2 text-xs text-emerald-700 dark:text-emerald-400">Super Admin</div>
                  @endif
                </td>
                <td class="px-4 py-3 align-top">
                  <form method="POST" action="{{ route('hrd.users.role.update', $user) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    @method('PATCH')
                    <select name="role" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" @disabled(!$canManageRoles || $user->isSuperAdmin())>
                      @foreach($roleOptions as $roleValue)
                        <option value="{{ $roleValue }}" @selected($roleValue === $currentRole)>{{ $roleLabels[$roleValue] ?? ucfirst($roleValue) }}</option>
                      @endforeach
                    </select>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60" @disabled(!$canManageRoles || $user->isSuperAdmin())>
                      Simpan
                    </button>
                  </form>
                  @if($user->isSuperAdmin())
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Akun super-admin tidak bisa didowngrade.</div>
                  @endif
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

      <div class="mt-4">{{ $users->links() }}</div>
    </div>
  </div>
</div>
@endsection
