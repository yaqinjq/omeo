@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-lg p-4">
  <div class="text-lg font-semibold">{{ $indicator->category }}</div>
  <div class="mt-3 whitespace-pre-line">{{ $indicator->question }}</div>
  @if($indicator->description)
  <div class="mt-2 text-sm text-slate-500">{{ $indicator->description }}</div>
  @endif

  @if(!empty($indicator->scale_labels))
  <div class="mt-4 rounded-lg border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-slate-50 text-left text-xs text-slate-500">
          <th class="px-3 py-2 w-20">Skor</th>
          <th class="px-3 py-2">Keterangan</th>
        </tr>
      </thead>
      <tbody>
        @for($star = 5; $star >= 1; $star--)
          @if(!empty($indicator->scale_labels[$star]))
          <tr class="border-t border-slate-100">
            <td class="px-3 py-2 font-semibold text-slate-600">{{ $star }}</td>
            <td class="px-3 py-2 text-slate-700">{{ $indicator->scale_labels[$star] }}</td>
          </tr>
          @endif
        @endfor
      </tbody>
    </table>
  </div>
  @endif
</div>
@endsection
