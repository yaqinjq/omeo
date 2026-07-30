@extends('layouts.app')

@section('content')
<div class="max-w-7xl space-y-4">
    <div>
        <h1 class="text-xl font-semibold">Edit Template Kontrak Daily Worker</h1>
        <p class="text-sm text-slate-600">Perbarui isi dan pengaturan penomoran template.</p>
    </div>

    <form method="POST" action="{{ route('hrd.contract-templates.update', $template) }}" enctype="multipart/form-data" class="bg-white border rounded-lg p-4">
        @csrf
        @method('PUT')
        @include('hrd.contract_templates._form', ['template' => $template, 'requiredPlaceholders' => $requiredPlaceholders])
    </form>
</div>
@endsection
