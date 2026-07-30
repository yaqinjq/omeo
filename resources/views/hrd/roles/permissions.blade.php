@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl p-4 sm:p-6">
  <div class="rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <div class="border-b px-6 py-4 dark:border-gray-700">
      <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Permissions: {{ $role->name }}</h1>
      <p class="text-sm text-gray-500">Atur akses per grup permission.</p>
      <div class="mt-2 flex gap-2">
        @if($role->is_system)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">system role</span>@endif
        @if($role->is_super_admin)<span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">super admin</span>@endif
      </div>
    </div>

    <div class="space-y-4 p-6">
      @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
      @endif

      <input id="permission-search" type="text" placeholder="Cari permission..." class="w-full rounded-lg border-gray-300 text-sm">

      <form method="POST" action="{{ route('hrd.roles.permissions.update', $role) }}" class="space-y-4">
        @csrf
        @method('PUT')

        @foreach($permissionsGrouped as $group => $permissions)
          <div class="rounded-xl border border-gray-200 p-4 permission-group" data-group="{{ strtolower($group) }}">
            <div class="mb-3 flex items-center justify-between">
              <div class="text-sm font-semibold text-gray-800">{{ $group }}</div>
              <button type="button" class="select-group rounded-lg bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Select all in group</button>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              @foreach($permissions as $permission)
                <label class="permission-item inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm" data-label="{{ strtolower($permission->name.' '.$permission->slug) }}">
                  <input type="checkbox" name="permissions[]" value="{{ $permission->slug }}" @checked(in_array($permission->slug, $currentSlugs, true)) @disabled($role->is_super_admin) class="rounded border-gray-300 text-blue-600">
                  <span>{{ $permission->name }}</span>
                  <span class="text-xs text-gray-400">{{ $permission->slug }}</span>
                </label>
              @endforeach
            </div>
          </div>
        @endforeach

        <div class="flex gap-2">
          <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @disabled($role->is_super_admin)>Simpan</button>
          <a href="{{ route('hrd.roles.index') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700">Kembali</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.select-group').forEach(function(btn){
  btn.addEventListener('click', function(){
    var wrapper = btn.closest('.permission-group');
    if (!wrapper) return;
    wrapper.querySelectorAll('input[type=checkbox]:not([disabled])').forEach(function(cb){ cb.checked = true; });
  });
});

document.getElementById('permission-search')?.addEventListener('input', function(e){
  var keyword = (e.target.value || '').toLowerCase().trim();
  document.querySelectorAll('.permission-item').forEach(function(item){
    var label = item.getAttribute('data-label') || '';
    item.style.display = keyword === '' || label.includes(keyword) ? '' : 'none';
  });
});
</script>
@endsection
