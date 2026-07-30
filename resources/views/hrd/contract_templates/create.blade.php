@extends('layouts.app')

@section('content')
<div class="max-w-7xl space-y-4">
    <div>
        <h1 class="text-xl font-semibold">Buat Template Kontrak Daily Worker</h1>
        <p class="text-sm text-slate-600">Template dipakai pada proses kontrak kandidat lolos.</p>
    </div>

    <form method="POST" action="{{ route('hrd.contract-templates.store') }}" enctype="multipart/form-data" class="bg-white border rounded-lg p-4">
        @csrf
        @include('hrd.contract_templates._form', ['template' => $template, 'requiredPlaceholders' => $requiredPlaceholders])
    </form>
</div>
@endsection
