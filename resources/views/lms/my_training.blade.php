@extends('layouts.app')
@section('content')
<div class="bg-white border rounded-2xl p-6">
  <h1 class="text-2xl font-semibold">My Training</h1>
  <p class="text-slate-500 text-sm mt-1">Daftar training yang di-assign untuk employee_id: <b>{{ $employeeId }}</b>.</p>

  <div class="mt-4 overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-left text-slate-500">
        <tr>
          <th class="py-2">Title</th>
          <th class="py-2">Category</th>
          <th class="py-2">Status</th>
          <th class="py-2">Score</th>
          <th class="py-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $it)
          <tr class="border-t">
            <td class="py-2">{{ $it->title }}</td>
            <td class="py-2">{{ $it->category }}</td>
            <td class="py-2">{{ $it->status }}</td>
            <td class="py-2">{{ $it->quiz_score ?? '-' }}</td>
            <td class="py-2">
              @if($it->youtube_url)
                <a class="text-blue-600 hover:underline" href="{{ $it->youtube_url }}" target="_blank">Buka video</a>
              @else
                -
              @endif
            </td>
          </tr>
        @empty
          <tr class="border-t">
            <td class="py-3 text-slate-500" colspan="5">Belum ada training yang di-assign.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
