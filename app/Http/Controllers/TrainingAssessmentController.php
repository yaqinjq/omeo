<?php

namespace App\Http\Controllers;

use App\Models\AssessmentForm;
use App\Models\FormQuestion;
use App\Models\TrainingFormAnswer;
use App\Models\TrainingFormAttempt;
use App\Models\TrainingMaterial;
use App\Models\TrainingProgram;
use App\Services\FormScoringService;
use App\Services\LmsService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TrainingAssessmentController extends Controller
{
    public function __construct(
        private readonly FormScoringService $scoringService,
        private readonly LmsService $lmsService,
    ) {
    }

    public function show(Request $request, TrainingProgram $program, TrainingMaterial $material, string $purpose)
    {
        $employee = $request->user()?->employee;
        abort_unless($employee, 403);
        abort_unless(in_array($purpose, ['pretest', 'posttest'], true), 404);

        $program->loadMissing('materials.pretestForm.questions.options', 'materials.posttestForm.questions.options');
        $programs = $this->lmsService->programsForEmployee($employee);
        $employeeProgram = $programs->firstWhere('id', $program->id);
        abort_unless($employeeProgram, 403);

        $targetMaterial = $employeeProgram->materials->firstWhere('id', $material->id);
        abort_unless($targetMaterial, 404);
        abort_if($targetMaterial->is_locked, 403, 'Materi masih terkunci.');

        $form = $purpose === 'pretest' ? $targetMaterial->pretestForm : $targetMaterial->posttestForm;
        abort_unless($form, 404);

        $attempt = TrainingFormAttempt::query()
            ->where('employee_id', $employee->id)
            ->where('training_program_id', $program->id)
            ->where('training_material_id', $material->id)
            ->where('form_id', $form->id)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $attempt) {
            $attempt = TrainingFormAttempt::create([
                'employee_id' => $employee->id,
                'training_program_id' => $program->id,
                'training_material_id' => $material->id,
                'form_id' => $form->id,
                'purpose' => $purpose,
                'status' => 'started',
                'started_at' => now(),
            ]);
        } elseif ($attempt->status === 'draft') {
            $attempt->update(['status' => 'started', 'started_at' => $attempt->started_at ?: now()]);
            $attempt->refresh();
        }

        $remainingSeconds = null;
        if ($form->duration_minutes && $attempt->started_at) {
            $remainingSeconds = max(0, now()->diffInSeconds($attempt->started_at->copy()->addMinutes((int) $form->duration_minutes), false));
        }

        return view('training_assessments.show', [
            'program' => $program,
            'material' => $targetMaterial,
            'form' => $form,
            'attempt' => $attempt->loadMissing('answers'),
            'purpose' => $purpose,
            'remainingSeconds' => $remainingSeconds,
        ]);
    }

    public function submit(Request $request, TrainingProgram $program, TrainingMaterial $material, string $purpose)
    {
        $employee = $request->user()?->employee;
        abort_unless($employee, 403);
        abort_unless(in_array($purpose, ['pretest', 'posttest'], true), 404);

        $program->loadMissing('materials.pretestForm.questions.options', 'materials.posttestForm.questions.options');
        $programs = $this->lmsService->programsForEmployee($employee);
        $employeeProgram = $programs->firstWhere('id', $program->id);
        abort_unless($employeeProgram, 403);
        $targetMaterial = $employeeProgram->materials->firstWhere('id', $material->id);
        abort_unless($targetMaterial, 404);

        $form = $purpose === 'pretest' ? $targetMaterial->pretestForm : $targetMaterial->posttestForm;
        abort_unless($form, 404);

        $attempt = TrainingFormAttempt::query()
            ->where('employee_id', $employee->id)
            ->where('training_program_id', $program->id)
            ->where('training_material_id', $material->id)
            ->where('form_id', $form->id)
            ->where('purpose', $purpose)
            ->latest('id')
            ->firstOrFail();

        if ($attempt->status === 'submitted') {
            return redirect()->route('training-assessments.show', [$program, $material, $purpose])->with('error', 'Tes ini sudah pernah dikirim.');
        }

        $questions = $form->questions;
        $normalized = $this->validateAndNormalizeAnswers($request, $questions, (string) $form->type);

        try {
            DB::transaction(function () use ($attempt, $questions, $normalized, $form, $employee, $program, $material, $purpose): void {
                $lockedAttempt = TrainingFormAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

                if (! $lockedAttempt->started_at) {
                    $lockedAttempt->update(['started_at' => now(), 'status' => 'started']);
                }

                if ($form->duration_minutes) {
                    $endsAt = $lockedAttempt->started_at->copy()->addMinutes((int) $form->duration_minutes);
                    if (now()->greaterThan($endsAt)) {
                        $lockedAttempt->update(['status' => 'expired']);
                        throw ValidationException::withMessages(['expired' => 'Waktu tes sudah habis.']);
                    }
                }

                TrainingFormAnswer::query()->where('training_form_attempt_id', $lockedAttempt->id)->delete();

                foreach ($questions as $question) {
                    $key = 'q_' . $question->id;
                    $entry = $normalized[$key] ?? null;
                    if (! $entry) {
                        continue;
                    }

                    TrainingFormAnswer::create([
                        'training_form_attempt_id' => $lockedAttempt->id,
                        'question_id' => $question->id,
                        'answer_text' => $entry['answer_text'],
                        'answer_value' => $entry['answer_value'],
                        'answer_json' => $entry['answer_json'],
                        'answer_file_path' => $entry['answer_file_path'],
                    ]);
                }

                $scoringPayload = array_map(fn ($item) => $item['raw'], $normalized);
                $computed = $this->scoringService->compute($form, $questions, $scoringPayload);
                $submittedAt = now();

                $lockedAttempt->update([
                    'status' => 'submitted',
                    'submitted_at' => $submittedAt,
                    'time_spent_seconds' => max(0, $lockedAttempt->started_at->diffInSeconds($submittedAt)),
                    'computed_result' => $computed,
                ]);

                $this->lmsService->storeAssessmentResult($employee, $program, $material, $purpose, $lockedAttempt->fresh());
            });
        } catch (ValidationException $exception) {
            return redirect()
                ->route('training-assessments.show', [$program, $material, $purpose])
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Gagal mengirim tes training.');
        }

        return redirect()->route('my-training.index')->with('success', ucfirst($purpose) . ' berhasil dikirim.');
    }

    private function validateAndNormalizeAnswers(Request $request, $questions, string $formType): array
    {
        $normalized = [];

        foreach ($questions as $question) {
            $key = 'q_' . $question->id;
            $input = $request->input($key);
            $fileInput = $request->file($key);
            $settings = is_array($question->settings ?? null) ? $question->settings : [];
            $type = $question->question_type;
            $required = (bool) $question->is_required;

            if ($formType === AssessmentForm::TYPE_DISC && ($settings['disc_mode'] ?? null) === 'dual_axis') {
                $hasMost = trim((string) data_get($input, 'most', '')) !== '';
                $hasLeast = trim((string) data_get($input, 'least', '')) !== '';

                if ($required && (! $hasMost || ! $hasLeast)) {
                    throw ValidationException::withMessages([$key => 'Untuk DISC, pilih jawaban paling sesuai dan paling tidak sesuai.']);
                }

                if (! $hasMost && ! $hasLeast) {
                    continue;
                }

                $mostId = (int) data_get($input, 'most', 0);
                $leastId = (int) data_get($input, 'least', 0);
                $available = $question->options->pluck('id')->map(fn ($id) => (int) $id)->all();

                if (! in_array($mostId, $available, true) || ! in_array($leastId, $available, true)) {
                    throw ValidationException::withMessages([$key => 'Pilihan jawaban DISC tidak valid.']);
                }

                if ($mostId === $leastId) {
                    throw ValidationException::withMessages([$key => 'Pilihan paling sesuai dan paling tidak sesuai tidak boleh sama.']);
                }

                $normalized[$key] = [
                    'answer_text' => null,
                    'answer_value' => null,
                    'answer_json' => ['most' => $mostId, 'least' => $leastId],
                    'answer_file_path' => null,
                    'raw' => ['most' => $mostId, 'least' => $leastId],
                ];
                continue;
            }

            if (in_array($type, FormQuestion::uploadTypes(), true)) {
                if ($required && ! $fileInput) {
                    throw ValidationException::withMessages([$key => 'Jawaban file untuk pertanyaan ini wajib diunggah.']);
                }

                if (! $fileInput) {
                    continue;
                }

                $normalized[$key] = $this->normalizeUploadAnswer($question, $fileInput, $settings);
                continue;
            }

            $hasValue = !($input === null || $input === '' || $input === []);
            if ($required && ! $hasValue) {
                throw ValidationException::withMessages([$key => 'Masih ada pertanyaan wajib yang belum diisi.']);
            }

            if (! $hasValue) {
                continue;
            }

            $payload = [
                'answer_text' => null,
                'answer_value' => null,
                'answer_json' => null,
                'answer_file_path' => null,
                'raw' => $input,
            ];

            if (in_array($type, [FormQuestion::TYPE_SHORT_TEXT, FormQuestion::TYPE_PARAGRAPH], true)) {
                $payload['answer_text'] = trim((string) $input);
            } elseif (in_array($type, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN], true)) {
                $optionId = (int) $input;
                $exists = $question->options->contains(fn ($option) => (int) $option->id === $optionId);
                if (! $exists) {
                    throw ValidationException::withMessages([$key => 'Pilihan jawaban tidak valid.']);
                }
                $payload['answer_value'] = (string) $optionId;
                $payload['raw'] = $optionId;
            } elseif ($type === FormQuestion::TYPE_CHECKBOX) {
                if (! is_array($input) || count($input) === 0) {
                    throw ValidationException::withMessages([$key => 'Pilih minimal satu jawaban.']);
                }
                $optionIds = array_values(array_unique(array_map('intval', $input)));
                $available = $question->options->pluck('id')->map(fn ($id) => (int) $id)->all();
                foreach ($optionIds as $optionId) {
                    if (! in_array($optionId, $available, true)) {
                        throw ValidationException::withMessages([$key => 'Pilihan jawaban tidak valid.']);
                    }
                }
                $payload['answer_json'] = $optionIds;
                $payload['raw'] = $optionIds;
            } elseif (in_array($type, [FormQuestion::TYPE_RATING, FormQuestion::TYPE_LINEAR_SCALE], true)) {
                $value = (int) $input;
                $min = (int) ($settings['min'] ?? 1);
                $max = (int) ($settings['max'] ?? 5);
                if ($value < $min || $value > $max) {
                    throw ValidationException::withMessages([$key => 'Nilai skala di luar rentang yang diizinkan.']);
                }
                $payload['answer_value'] = (string) $value;
                $payload['raw'] = $value;
            }

            $normalized[$key] = $payload;
        }

        return $normalized;
    }

    private function normalizeUploadAnswer(FormQuestion $question, UploadedFile $file, array $settings): array
    {
        $maxKb = (int) ($settings['answer_max_kb'] ?? 3072);
        if ($maxKb > 0 && ($file->getSize() / 1024) > $maxKb) {
            throw ValidationException::withMessages([
                'q_' . $question->id => 'Ukuran file melebihi batas ' . $maxKb . ' KB.',
            ]);
        }

        $accept = trim((string) ($settings['answer_accept'] ?? ''));
        if ($accept !== '') {
            $this->assertAcceptedFile($question, $file, $accept);
        } elseif ($question->question_type === FormQuestion::TYPE_IMAGE_UPLOAD && ! str_starts_with((string) $file->getMimeType(), 'image/')) {
            throw ValidationException::withMessages([
                'q_' . $question->id => 'Pertanyaan ini hanya menerima file gambar.',
            ]);
        }

        $path = $file->store('training/answers', 'public');

        return [
            'answer_text' => $file->getClientOriginalName(),
            'answer_value' => $path,
            'answer_json' => [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_kb' => (int) ceil($file->getSize() / 1024),
            ],
            'answer_file_path' => $path,
            'raw' => $path,
        ];
    }

    private function assertAcceptedFile(FormQuestion $question, UploadedFile $file, string $accept): void
    {
        $tokens = collect(explode(',', $accept))
            ->map(fn ($item) => trim(strtolower($item)))
            ->filter()
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $extension = '.' . strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());

        $accepted = $tokens->contains(function (string $token) use ($extension, $mime) {
            if ($token === 'image/*') {
                return str_starts_with($mime, 'image/');
            }

            if ($token === 'video/*') {
                return str_starts_with($mime, 'video/');
            }

            if (str_starts_with($token, '.')) {
                return $extension === $token;
            }

            return $mime === $token;
        });

        if (! $accepted) {
            throw ValidationException::withMessages([
                'q_' . $question->id => 'Format file tidak sesuai dengan aturan pertanyaan.',
            ]);
        }
    }
}
