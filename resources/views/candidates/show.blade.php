@extends('layouts.app')

@section('content')
@php
  use App\Models\AssessmentForm;

  $assessment = $candidate->assessment;
  $disc = $assessment?->disc_result ?? [];
  $assignableTypes = $assignableTypes ?? AssessmentForm::assignableTypes();
  $applicationPosition = $candidate->application_position_name ?? $candidate->applied_position_name ?? '-';
  $applicationDepartment = $candidate->application_department_name ?? $candidate->applied_department_name ?? '-';
  $applicationOutlet = $candidate->application_outlet_name ?? $candidate->applied_outlet_name ?? '-';
@endphp

<div class="space-y-4">
  <div class="bg-white border rounded-lg p-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div>
        <div class="text-lg font-semibold">{{ $candidate->full_name }}</div>
        <div class="text-sm text-slate-600">{{ $candidate->email }} | {{ $candidate->phone }} | {{ $candidate->status }}</div>
        @if($canRestore && $restoreDeadline)
          <div class="text-xs text-amber-600 mt-1">Restore tersedia sampai {{ $restoreDeadline->format('d/m/Y H:i') }}</div>
        @endif
      </div>
      <div class="flex flex-wrap items-center gap-2">
        @if($canRestore)
          <form method="POST" action="{{ route('candidates.restore', $candidate) }}">
            @csrf
            <button class="px-3 py-2 rounded border border-blue-200 text-blue-700">Restore</button>
          </form>
        @endif
        <a class="px-3 py-2 rounded border" href="{{ route('candidates.export-preview', $candidate) }}">Preview PDF</a>
        <a class="px-3 py-2 rounded border" href="{{ route('candidates.index') }}">Kembali</a>
      </div>
    </div>

    @if($candidate->notes)
      <div class="mt-3 text-sm text-slate-700 whitespace-pre-line">{{ $candidate->notes }}</div>
    @endif
  </div>

  <div class="bg-white border rounded-lg p-4 space-y-4">
    <div>
      <h2 class="font-semibold">Lamaran Kandidat</h2>
      <div class="text-xs text-slate-500 mt-1">Ringkasan posisi yang dilamar dan preferensi penempatan kandidat.</div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
      <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
        <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">Posisi yang Dilamar</div>
        <div class="mt-2 text-base font-semibold text-slate-900">{{ $applicationPosition !== '' ? $applicationPosition : '-' }}</div>
      </div>
      <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Departemen Diminati</div>
        <div class="mt-2 text-base font-semibold text-slate-900">{{ $applicationDepartment !== '' ? $applicationDepartment : '-' }}</div>
      </div>
      <div class="rounded-lg border border-amber-100 bg-amber-50 p-3">
        <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Outlet Diminati</div>
        <div class="mt-2 text-base font-semibold text-slate-900">{{ $applicationOutlet !== '' ? $applicationOutlet : '-' }}</div>
      </div>
    </div>
  </div>

  <div class="bg-white border rounded-lg p-4 space-y-3">
    <h2 class="font-semibold">Ringkasan Hasil Test</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 text-sm">
      <div class="border rounded p-3">
        <div class="text-xs text-slate-500">IQ Score</div>
        <div class="font-semibold text-lg">{{ $assessment?->iq_score ?? '-' }}</div>
      </div>
      <div class="border rounded p-3 md:col-span-2 xl:col-span-3">
        <div class="text-xs text-slate-500">DISC Summary</div>
        @if(!empty($disc))
          <div class="font-semibold">D: {{ (int) ($disc['D'] ?? 0) }} | I: {{ (int) ($disc['I'] ?? 0) }} | S: {{ (int) ($disc['S'] ?? 0) }} | C: {{ (int) ($disc['C'] ?? 0) }}</div>
          @if(is_array(data_get($disc, 'summary')))
            <div class="text-xs text-slate-500 mt-1">Dominan: {{ data_get($disc, 'summary.label') ?? data_get($disc, 'summary.dominant_axis') }}</div>
          @endif
        @else
          <div class="font-semibold">-</div>
        @endif
      </div>
    </div>

    <div class="space-y-2">
      @foreach($candidate->formAssignments->sortByDesc('id') as $assignment)
        @php
          $computedResult = is_array($assignment->attempt?->computed_result) ? $assignment->attempt->computed_result : [];
          $score = data_get($computedResult, 'score');
          $dominantDisc = data_get($computedResult, 'summary.label') ?? data_get($computedResult, 'summary.dominant_axis');
        @endphp
        <div class="border rounded p-3 text-sm">
          <div class="font-medium">{{ $assignment->form->name ?? 'Form' }} ({{ strtoupper(AssessmentForm::labelFor($assignment->form->type ?? '-')) }})</div>
          <div>Status: <b>{{ $assignment->status }}</b></div>
          <div>Mulai: {{ $assignment->attempt?->started_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
          <div>Submit: {{ $assignment->attempt?->submitted_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
          <div>Durasi (detik): {{ $assignment->attempt?->time_spent_seconds ?? '-' }}</div>
          @if(!is_null($score))
            <div>Score: <b>{{ $score }}</b></div>
          @endif
          @if($assignment->form?->type === AssessmentForm::TYPE_DISC && $dominantDisc)
            <div>DISC Dominan: <b>{{ $dominantDisc }}</b></div>
          @endif
        </div>
      @endforeach
    </div>
  </div>

  <div class="bg-white border rounded-lg p-4">
    <h2 class="font-semibold mb-3">Manual Input Penilaian (Skip Form)</h2>
    <form method="POST" action="{{ route('candidates.assessment.update', $candidate) }}" class="space-y-3">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">IQ Score</label>
          <input type="number" min="0" max="300" name="iq_score" value="{{ old('iq_score', $assessment?->iq_score) }}" class="w-full border rounded p-2">
        </div>
        <div>
          <label class="text-sm">Interview Score</label>
          <input type="number" min="0" max="100" name="interview_score" value="{{ old('interview_score', $assessment?->interview_score) }}" class="w-full border rounded p-2">
        </div>
        <div>
          <label class="text-sm">DISC - D</label>
          <input type="number" min="0" name="disc_d" value="{{ old('disc_d', $disc['D'] ?? null) }}" class="w-full border rounded p-2">
        </div>
        <div>
          <label class="text-sm">DISC - I</label>
          <input type="number" min="0" name="disc_i" value="{{ old('disc_i', $disc['I'] ?? null) }}" class="w-full border rounded p-2">
        </div>
        <div>
          <label class="text-sm">DISC - S</label>
          <input type="number" min="0" name="disc_s" value="{{ old('disc_s', $disc['S'] ?? null) }}" class="w-full border rounded p-2">
        </div>
        <div>
          <label class="text-sm">DISC - C</label>
          <input type="number" min="0" name="disc_c" value="{{ old('disc_c', $disc['C'] ?? null) }}" class="w-full border rounded p-2">
        </div>
      </div>

      <div>
        <label class="text-sm">Catatan Interview</label>
        <textarea name="interview_notes" class="w-full border rounded p-2" rows="3">{{ old('interview_notes', $assessment?->interview_notes) }}</textarea>
      </div>

      <div>
        <label class="text-sm">Status Recruitment</label>
        <select name="status" class="w-full border rounded p-2">
          @foreach(['in_process','passed','reserve','rejected','blocked'] as $status)
            <option value="{{ $status }}" @selected(old('status', $assessment?->status ?? 'in_process') === $status)>{{ $status }}</option>
          @endforeach
        </select>
      </div>

      <button class="px-4 py-2 rounded bg-slate-900 text-white">Simpan Penilaian</button>
    </form>
  </div>

  <div class="bg-white border rounded-lg p-4 space-y-4">
    <h2 class="font-semibold">Kontrol Test Kandidat</h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 xl:grid-cols-3">
      @foreach($assignableTypes as $type => $label)
        @php
          $formList = $forms[$type] ?? collect();
          $theme = match ($type) {
            AssessmentForm::TYPE_DISC => 'bg-purple-600',
            AssessmentForm::TYPE_TIU => 'bg-emerald-600',
            AssessmentForm::TYPE_DIFERENSIAL => 'bg-amber-600',
            AssessmentForm::TYPE_FAT => 'bg-rose-600',
            default => 'bg-blue-600',
          };
        @endphp
        <div class="border rounded p-3 space-y-3">
          <h3 class="font-medium">{{ $label }}</h3>
          <form method="POST" action="{{ route('candidates.tests.open', $candidate) }}" class="space-y-2">
            @csrf
            <select name="form_id" class="w-full border rounded p-2" required>
              <option value="">Pilih {{ $label }}</option>
              @foreach($formList as $f)
                <option value="{{ $f->id }}">{{ $f->name }} ({{ $f->duration_minutes ?? '-' }} menit)</option>
              @endforeach
            </select>
            <button class="px-3 py-2 rounded {{ $theme }} text-white">Buka {{ $label }}</button>
          </form>

          @foreach($candidate->formAssignments->filter(fn ($assignment) => $assignment->form?->type === $type) as $assignment)
            <div class="border rounded p-2 text-sm space-y-2">
              <div class="font-medium">{{ $assignment->form->name }}</div>
              <div>Status: <b>{{ $assignment->status }}</b></div>
              <div>Waktu: {{ $assignment->opened_at?->format('d/m/Y H:i') ?? '-' }} - {{ $assignment->expires_at?->format('d/m/Y H:i') ?? '-' }}</div>
              <div class="flex gap-2">
                <form method="POST" action="{{ route('candidates.tests.lock', [$candidate, $assignment]) }}">@csrf<button class="px-2 py-1 border rounded">Kunci Test</button></form>
                <form method="POST" action="{{ route('candidates.tests.reset', [$candidate, $assignment]) }}" onsubmit="return confirm('Reset attempt test ini?')">@csrf<button class="px-2 py-1 border rounded text-red-600">Reset Attempt</button></form>
              </div>
            </div>
          @endforeach
        </div>
      @endforeach
    </div>
  </div>

  <div class="bg-white border rounded-lg p-4 space-y-3">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold">Audit Trail Recruitment</h2>
      <div class="text-xs text-slate-500">Append-only log untuk jejak aksi HRD</div>
    </div>

    <div class="space-y-2">
      @forelse($candidate->activityLogs->take(30) as $activity)
        <div class="border rounded p-3 text-sm">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
              <div class="font-medium">{{ $activity->action_type }}</div>
              <div class="text-xs text-slate-500">
                {{ $activity->actor?->name ?? 'System/Unknown' }}
                @if($activity->old_status || $activity->new_status)
                  | {{ $activity->old_status ?? '-' }} -> {{ $activity->new_status ?? '-' }}
                @endif
              </div>
            </div>
            <div class="text-xs text-slate-500">{{ $activity->created_at?->format('d/m/Y H:i:s') }}</div>
          </div>

          @if(!empty($activity->metadata))
            <pre class="mt-2 text-xs bg-slate-50 border rounded p-2 overflow-auto whitespace-pre-wrap">{{ json_encode($activity->metadata, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
          @endif

          <div class="mt-2 text-[11px] text-slate-400">
            IP: {{ $activity->ip_address ?: '-' }} | Source: {{ $activity->source_page ?: '-' }}
          </div>
        </div>
      @empty
        <div class="text-sm text-slate-500">Belum ada audit trail recruitment untuk kandidat ini.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
