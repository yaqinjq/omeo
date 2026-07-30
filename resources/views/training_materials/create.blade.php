@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4 space-y-4">
  <div class="text-lg font-semibold">Tambah Materi Training</div>
  <form method="POST" action="{{ route('training-materials.store') }}" class="space-y-4">
    @csrf
    @include('training_materials._form', ['material' => $material])
    <div class="flex gap-2"><button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan</button><a class="px-4 py-2 rounded border" href="{{ route('training-materials.index') }}">Kembali</a></div>
  </form>
</div>
@endsection
