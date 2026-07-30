@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4 space-y-4">
  <div class="text-lg font-semibold">Edit Materi Training</div>
  <form method="POST" action="{{ route('training-materials.update', $material) }}" class="space-y-4">
    @csrf
    @method('PUT')
    @include('training_materials._form', ['material' => $material])
    <div class="flex gap-2"><button class="px-4 py-2 rounded bg-slate-900 text-white">Update</button><a class="px-4 py-2 rounded border" href="{{ route('training-materials.index') }}">Kembali</a></div>
  </form>
</div>
@endsection
