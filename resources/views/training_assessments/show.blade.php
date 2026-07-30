@extends('layouts.app')
@section('content')
@php
  use App\Models\FormQuestion;
  use Illuminate\Support\Facades\Storage;

  $status = $attempt->status;
  $isSubmitted = $status === 'submitted';
@endphp
<div class="bg-white border rounded-lg p-4 space-y-4">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-lg font-semibold">{{ strtoupper($purpose) }}: {{ $form->name }}</h1>
      <p class="text-sm text-slate-600">Program: {{ $program->name }} | Materi: {{ $material->title }}</p>
    </div>
    <a href="{{ route('my-training.index') }}" class="px-3 py-2 rounded border">Kembali</a>
  </div>

  @if(session('error'))
    <div class="p-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="p-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm">
      <div class="font-semibold mb-1">Masih ada input yang perlu diperbaiki:</div>
      <ul class="list-disc ml-5 space-y-1">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
    <div class="border rounded p-3"><div class="text-xs text-slate-500">Status</div><div class="font-semibold">{{ ucfirst($status) }}</div></div>
    <div class="border rounded p-3"><div class="text-xs text-slate-500">Durasi</div><div class="font-semibold">{{ $form->duration_minutes ?: '-' }} menit</div></div>
    <div class="border rounded p-3"><div class="text-xs text-slate-500">Sisa Waktu</div><div class="font-semibold">{{ !is_null($remainingSeconds) ? gmdate('H:i:s', $remainingSeconds) : '-' }}</div></div>
  </div>

  @if($isSubmitted)
    <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
      {{ strtoupper($purpose) }} sudah dikirim. Skor: <strong>{{ data_get($attempt->computed_result, 'score', data_get($attempt->computed_result, 'iq_score', '-')) }}</strong>
    </div>
  @else
    <form method="POST" action="{{ route('training-assessments.submit', [$program, $material, $purpose]) }}" enctype="multipart/form-data" class="space-y-4">
      @csrf
      @foreach($form->questions as $question)
        @php
          $settings = is_array($question->settings ?? null) ? $question->settings : [];
          $isDualDisc = $form->type === \App\Models\AssessmentForm::TYPE_DISC && (($settings['disc_mode'] ?? null) === 'dual_axis');
          $mediaTitle = $settings['media_title'] ?? null;
          $mediaUrl = $settings['media_url'] ?? null;
          $youtubeUrl = $settings['youtube_url'] ?? null;
          $uploadAccept = $settings['answer_accept'] ?? ($question->question_type === FormQuestion::TYPE_IMAGE_UPLOAD ? 'image/*' : null);
          $uploadMaxKb = $settings['answer_max_kb'] ?? 3072;
        @endphp
        <div class="border rounded p-4 space-y-3">
          <div class="font-medium">{{ $loop->iteration }}. {{ $question->question_text }} @if($question->is_required)<span class="text-red-600">*</span>@endif</div>

          @if($question->question_image_path)
            <div class="rounded border border-slate-200 bg-slate-50 p-2">
              <img src="{{ Storage::disk('public')->url($question->question_image_path) }}" alt="Soal {{ $question->id }}" class="max-h-72 rounded">
            </div>
          @endif

          @if($mediaUrl || $youtubeUrl)
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 space-y-2">
              <div class="text-sm font-semibold text-indigo-900">{{ $mediaTitle ?: 'Media Pendukung Soal' }}</div>
              @if($youtubeUrl)
                <div class="aspect-video overflow-hidden rounded-lg bg-black">
                  <iframe src="{{ str_contains($youtubeUrl, 'embed') ? $youtubeUrl : preg_replace('/watch\?v=([^&]+)/', 'embed/$1', str_replace('youtu.be/', 'youtube.com/embed/', $youtubeUrl)) }}" class="h-full w-full" allowfullscreen loading="lazy"></iframe>
                </div>
                <a href="{{ $youtubeUrl }}" target="_blank" class="text-xs text-indigo-700 underline">Buka YouTube di tab baru</a>
              @endif
              @if($mediaUrl)
                <a href="{{ $mediaUrl }}" target="_blank" class="inline-flex items-center gap-2 text-sm text-indigo-700 underline">Buka media pendukung</a>
              @endif
            </div>
          @endif

          @if($isDualDisc)
            <div class="grid gap-3 md:grid-cols-2">
              <div class="rounded border border-emerald-200 bg-emerald-50 p-3 space-y-2">
                <div class="text-sm font-semibold text-emerald-900">Paling Sesuai</div>
                @foreach($question->options as $option)
                  <label class="flex items-start gap-2 text-sm"><input type="radio" name="q_{{ $question->id }}[most]" value="{{ $option->id }}"> <span>{{ $option->option_text }}</span></label>
                @endforeach
              </div>
              <div class="rounded border border-rose-200 bg-rose-50 p-3 space-y-2">
                <div class="text-sm font-semibold text-rose-900">Paling Tidak Sesuai</div>
                @foreach($question->options as $option)
                  <label class="flex items-start gap-2 text-sm"><input type="radio" name="q_{{ $question->id }}[least]" value="{{ $option->id }}"> <span>{{ $option->option_text }}</span></label>
                @endforeach
              </div>
            </div>
          @elseif($question->question_type === FormQuestion::TYPE_SHORT_TEXT)
            <input type="text" name="q_{{ $question->id }}" class="w-full border rounded p-2">
          @elseif($question->question_type === FormQuestion::TYPE_PARAGRAPH)
            <textarea name="q_{{ $question->id }}" rows="3" class="w-full border rounded p-2"></textarea>
          @elseif($question->question_type === FormQuestion::TYPE_RADIO)
            @foreach($question->options as $option)
              <label class="block text-sm"><input type="radio" name="q_{{ $question->id }}" value="{{ $option->id }}"> {{ $option->option_text }}</label>
            @endforeach
          @elseif($question->question_type === FormQuestion::TYPE_CHECKBOX)
            @foreach($question->options as $option)
              <label class="block text-sm"><input type="checkbox" name="q_{{ $question->id }}[]" value="{{ $option->id }}"> {{ $option->option_text }}</label>
            @endforeach
          @elseif($question->question_type === FormQuestion::TYPE_DROPDOWN)
            <select name="q_{{ $question->id }}" class="w-full border rounded p-2"><option value="">Pilih jawaban</option>@foreach($question->options as $option)<option value="{{ $option->id }}">{{ $option->option_text }}</option>@endforeach</select>
          @elseif(in_array($question->question_type, [FormQuestion::TYPE_RATING, FormQuestion::TYPE_LINEAR_SCALE], true))
            @php($min = (int) ($settings['min'] ?? 1))
            @php($max = (int) ($settings['max'] ?? 5))
            <select name="q_{{ $question->id }}" class="w-full border rounded p-2"><option value="">Pilih nilai</option>@for($i = $min; $i <= $max; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select>
          @elseif($question->question_type === FormQuestion::TYPE_IMAGE_UPLOAD)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 space-y-2 text-sm">
              <div class="font-semibold text-amber-900">Upload Foto Jawaban</div>
              <div class="text-amber-800">Format: {{ $uploadAccept ?: 'image/*' }} | Maksimum: {{ $uploadMaxKb }} KB</div>
              <input type="file" name="q_{{ $question->id }}" accept="{{ $uploadAccept ?: 'image/*' }}" capture="environment" class="block w-full text-sm">
            </div>
          @elseif($question->question_type === FormQuestion::TYPE_FILE_UPLOAD)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 space-y-2 text-sm">
              <div class="font-semibold text-amber-900">Upload File Jawaban</div>
              <div class="text-amber-800">Format: {{ $uploadAccept ?: '.pdf,.doc,.docx,.jpg,.jpeg,.png,.mp4' }} | Maksimum: {{ $uploadMaxKb }} KB</div>
              <input type="file" name="q_{{ $question->id }}" accept="{{ $uploadAccept ?: '.pdf,.doc,.docx,.jpg,.jpeg,.png,.mp4' }}" class="block w-full text-sm">
            </div>
          @endif
        </div>
      @endforeach

      <button class="px-4 py-2 rounded bg-slate-900 text-white" onclick="return confirm('Kirim {{ strtoupper($purpose) }} sekarang?')">Submit {{ strtoupper($purpose) }}</button>
    </form>
  @endif
</div>
@endsection
