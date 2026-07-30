@extends('layouts.app')

@section('content')
@php
  use App\Models\AssessmentForm;

  $status = $assignment->status;
  $isOpened = $status === 'opened';
  $startRequired = $isOpened && ($canStart ?? false);
  $isDiscDualAxis = $assignment->form->type === AssessmentForm::TYPE_DISC;
@endphp

<div class="bg-white border rounded-lg p-4 space-y-4">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-lg font-semibold">{{ $assignment->form->name }}</h1>
      <p class="text-sm text-slate-600">Status: <b>{{ $status }}</b></p>
    </div>
    <a href="{{ route('applicant.tests.index') }}" class="px-3 py-2 rounded border">Kembali</a>
  </div>

  @if(session('error'))
    <div class="p-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
      {{ session('error') }}
    </div>
  @endif

  @if($status === 'locked')
    <div class="p-3 rounded bg-amber-50 border border-amber-200 text-amber-800">
      Test belum dibuka oleh HRD.
    </div>
  @elseif(in_array($status, ['submitted', 'expired'], true))
    <div class="p-3 rounded {{ $status === 'submitted' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' }} border">
      @if($status === 'submitted')
        Test sudah dikirim. Anda tidak dapat mengerjakan ulang.
      @else
        Waktu test habis (expired). Anda tidak dapat melanjutkan.
      @endif
    </div>
  @elseif($isOpened)
    <div id="timerCard" class="p-3 rounded bg-blue-50 border border-blue-200 text-blue-800 text-sm space-y-1">
      <div>Batas waktu: {{ $endsAt?->format('d/m/Y H:i:s') ?? '-' }}</div>
      <div id="countdown" data-ends-at="{{ $endsAt?->toIso8601String() }}" data-server-now="{{ $serverNow?->toIso8601String() }}">
        Sisa waktu: {{ !is_null($remainingSeconds) ? gmdate('H:i:s', $remainingSeconds) : '-' }}
      </div>
    </div>

    @if($isDiscDualAxis)
      <div class="rounded border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-900">
        Untuk setiap soal DISC, pilih <b>satu opsi yang paling sesuai</b> dan <b>satu opsi yang paling tidak sesuai</b>. Pilihan tidak boleh sama.
      </div>
    @endif

    <div id="startModal" class="{{ $startRequired ? '' : 'hidden ' }}fixed inset-0 z-40 bg-slate-900/50 flex items-center justify-center px-4">
      <div class="bg-white w-full max-w-md rounded-xl shadow-xl p-5 space-y-4">
        <h2 class="text-lg font-semibold">Test Sudah Aktif</h2>
        <p class="text-sm text-slate-600">Test sudah aktif. Klik Mulai untuk memulai timer.</p>
        <div class="flex justify-end gap-2">
          <a href="{{ route('applicant.tests.index') }}" class="px-3 py-2 rounded border text-sm">Kembali</a>
          <button id="startButton" class="px-3 py-2 rounded bg-slate-900 text-white text-sm">Mulai Test</button>
        </div>
        <div id="startError" class="hidden text-sm text-red-700 bg-red-50 border border-red-200 rounded p-2"></div>
      </div>
    </div>

    <form id="testForm" method="POST" action="{{ route('applicant.tests.submit', $assignment) }}" class="space-y-4 {{ $startRequired ? 'opacity-50 pointer-events-none' : '' }}">
      @csrf
      @foreach($assignment->form->questions as $question)
        @php
          $discMode = data_get($question->settings, 'disc_mode', 'single_axis');
          $isDualAxisQuestion = $assignment->form->type === AssessmentForm::TYPE_DISC && $discMode === 'dual_axis';
        @endphp
        <div class="border rounded p-3 space-y-3">
          <div class="font-medium">
            {{ $loop->iteration }}. {{ $question->question_text }}
            @if($question->is_required)<span class="text-red-600">*</span>@endif
          </div>

          @if($question->question_image_path)
            <div class="rounded border border-slate-200 bg-slate-50 p-2">
              <img src="{{ Storage::disk('public')->url($question->question_image_path) }}" alt="Soal {{ $question->id }}" class="max-h-72 rounded">
            </div>
          @endif

          @if($isDualAxisQuestion)
            <div class="grid gap-3 md:grid-cols-2">
              <div class="rounded border border-emerald-200 bg-emerald-50 p-3 space-y-2">
                <div class="text-sm font-semibold text-emerald-900">Paling Sesuai</div>
                @foreach($question->options as $option)
                  <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="radio" name="q_{{ $question->id }}[most]" value="{{ $option->id }}" {{ old('q_' . $question->id . '.most') == $option->id ? 'checked' : '' }}>
                    <span>{{ $option->option_text }}</span>
                  </label>
                @endforeach
              </div>
              <div class="rounded border border-rose-200 bg-rose-50 p-3 space-y-2">
                <div class="text-sm font-semibold text-rose-900">Paling Tidak Sesuai</div>
                @foreach($question->options as $option)
                  <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="radio" name="q_{{ $question->id }}[least]" value="{{ $option->id }}" {{ old('q_' . $question->id . '.least') == $option->id ? 'checked' : '' }}>
                    <span>{{ $option->option_text }}</span>
                  </label>
                @endforeach
              </div>
            </div>
          @elseif($question->question_type === 'short_text')
            <input type="text" name="q_{{ $question->id }}" value="{{ old('q_' . $question->id) }}" class="w-full border rounded p-2">
          @elseif($question->question_type === 'paragraph')
            <textarea name="q_{{ $question->id }}" rows="3" class="w-full border rounded p-2">{{ old('q_' . $question->id) }}</textarea>
          @elseif($question->question_type === 'radio')
            @foreach($question->options as $option)
              <label class="block text-sm"><input type="radio" name="q_{{ $question->id }}" value="{{ $option->id }}" {{ old('q_' . $question->id) == $option->id ? 'checked' : '' }}> {{ $option->option_text }}</label>
            @endforeach
          @elseif($question->question_type === 'checkbox')
            @php($checkedValues = collect(old('q_' . $question->id, []))->map(fn ($value) => (string) $value)->all())
            @foreach($question->options as $option)
              <label class="block text-sm"><input type="checkbox" name="q_{{ $question->id }}[]" value="{{ $option->id }}" {{ in_array((string) $option->id, $checkedValues, true) ? 'checked' : '' }}> {{ $option->option_text }}</label>
            @endforeach
          @elseif($question->question_type === 'dropdown')
            <select name="q_{{ $question->id }}" class="w-full border rounded p-2">
              <option value="">Pilih jawaban</option>
              @foreach($question->options as $option)
                <option value="{{ $option->id }}" {{ old('q_' . $question->id) == $option->id ? 'selected' : '' }}>{{ $option->option_text }}</option>
              @endforeach
            </select>
          @elseif(in_array($question->question_type, ['rating', 'linear_scale'], true))
            @php
              $min = (int) data_get($question->settings, 'min', 1);
              $max = (int) data_get($question->settings, 'max', 5);
            @endphp
            <select name="q_{{ $question->id }}" class="w-full border rounded p-2">
              <option value="">Pilih nilai</option>
              @for($i = $min; $i <= $max; $i++)
                <option value="{{ $i }}" {{ old('q_' . $question->id) == $i ? 'selected' : '' }}>{{ $i }}</option>
              @endfor
            </select>
          @endif
        </div>
      @endforeach

      <button id="submitBtn" class="px-4 py-2 rounded bg-slate-900 text-white" onclick="return confirm('Kirim jawaban sekarang?')">Submit Test</button>
    </form>

    <script>
      (function () {
        const countdownEl = document.getElementById('countdown');
        const formEl = document.getElementById('testForm');
        const submitBtn = document.getElementById('submitBtn');
        const modalEl = document.getElementById('startModal');
        const startBtn = document.getElementById('startButton');
        const startErr = document.getElementById('startError');
        const csrf = '{{ csrf_token() }}';

        if (!countdownEl || !formEl) return;

        let endsAt = countdownEl.dataset.endsAt ? new Date(countdownEl.dataset.endsAt).getTime() : null;
        let serverOffset = countdownEl.dataset.serverNow ? (new Date(countdownEl.dataset.serverNow).getTime() - Date.now()) : 0;

        const enableForm = () => {
          formEl.classList.remove('opacity-50', 'pointer-events-none');
          if (modalEl) modalEl.classList.add('hidden');
        };

        const disableForm = () => {
          formEl.classList.add('opacity-50', 'pointer-events-none');
          formEl.querySelectorAll('input, textarea, select, button').forEach((el) => {
            el.disabled = true;
          });
          if (submitBtn) {
            submitBtn.textContent = 'Waktu Habis';
          }
        };

        const render = () => {
          if (!endsAt) {
            countdownEl.textContent = 'Sisa waktu: -';
            return;
          }

          const nowServer = Date.now() + serverOffset;
          const left = Math.max(0, Math.floor((endsAt - nowServer) / 1000));
          const h = String(Math.floor(left / 3600)).padStart(2, '0');
          const m = String(Math.floor((left % 3600) / 60)).padStart(2, '0');
          const s = String(left % 60).padStart(2, '0');
          countdownEl.textContent = 'Sisa waktu: ' + h + ':' + m + ':' + s;

          if (left <= 0) {
            disableForm();
            setTimeout(() => {
              location.reload();
            }, 1200);
          }
        };

        if (startBtn) {
          startBtn.addEventListener('click', async function () {
            startBtn.disabled = true;
            if (startErr) {
              startErr.classList.add('hidden');
              startErr.textContent = '';
            }

            try {
              const response = await fetch('{{ route('applicant.tests.start', $assignment) }}', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': csrf,
                  'Accept': 'application/json',
                },
                body: JSON.stringify({})
              });

              const data = await response.json();

              if (!response.ok) {
                throw new Error(data.message || 'Gagal memulai test.');
              }

              endsAt = new Date(data.ends_at).getTime();
              serverOffset = new Date(data.server_now).getTime() - Date.now();
              enableForm();
              render();
            } catch (error) {
              if (startErr) {
                startErr.textContent = error.message;
                startErr.classList.remove('hidden');
              }
              startBtn.disabled = false;
            }
          });
        }

        if (!modalEl || modalEl.classList.contains('hidden')) {
          enableForm();
        }

        render();
        setInterval(render, 1000);
      })();
    </script>
  @endif
</div>
@endsection
