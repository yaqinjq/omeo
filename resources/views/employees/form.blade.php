@extends('layouts.app')
@section('content')
@php $isEdit = $mode === 'edit'; @endphp

<div class="bg-white border rounded-2xl p-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">{{ $isEdit ? 'Edit' : 'Tambah' }} Karyawan</h1>
    <a href="{{ route('employees.index') }}" class="px-3 py-2 rounded-lg border hover:bg-slate-50">Kembali</a>
  </div>

  <form method="POST" action="{{ $isEdit ? route('employees.update',$row->id) : route('employees.store') }}" class="mt-6 space-y-4">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      @foreach($cols as $c)
        @if(!in_array($c, ['id','created_at','updated_at','deleted_at']))
          <div>
            <label class="text-sm font-medium">{{ $c }}</label>
            <input name="{{ $c }}" value="{{ old($c, $row?->{$c} ?? '') }}" class="mt-1 w-full rounded-lg border px-3 py-2" />
          </div>
        @endif
      @endforeach
    </div>

    <button class="px-4 py-2 rounded-lg bg-slate-900 text-white">{{ $isEdit ? 'Update' : 'Simpan' }}</button>
  </form>
</div>
@endsection
