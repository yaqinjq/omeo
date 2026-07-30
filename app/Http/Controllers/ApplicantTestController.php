<?php

namespace App\Http\Controllers;

use App\Models\AssessmentForm;
use App\Models\Candidate;
use App\Models\CandidateAssessment;
use App\Models\FormAnswer;
use App\Models\FormAssignment;
use App\Models\FormAttempt;
use App\Models\FormQuestion;
use App\Services\FormScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicantTestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $candidate = $user ? $user->resolveCandidate() : null;

        $assignableTypes = AssessmentForm::assignableTypes();
        $assignmentsByType = collect(array_fill_keys(array_keys($assignableTypes), null));
        $popupAssignment = null;

        if ($candidate) {
            $assignments = FormAssignment::query()
                ->with(['attempt', 'form:id,name,type,duration_minutes,is_active'])
                ->where('candidate_id', $candidate->id)
                ->whereHas('form', function ($query) use ($assignableTypes) {
                    $query->whereIn('type', array_keys($assignableTypes));
                })
                ->orderByDesc('id')
                ->get();

            foreach ($assignments as $assignment) {
                $this->expireIfNeeded($assignment);
            }

            $assignments = FormAssignment::query()
                ->with(['attempt', 'form:id,name,type,duration_minutes,is_active'])
                ->where('candidate_id', $candidate->id)
                ->whereHas('form', function ($query) use ($assignableTypes) {
                    $query->whereIn('type', array_keys($assignableTypes));
                })
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            foreach (array_keys($assignableTypes) as $type) {
                $assignmentsByType[$type] = $this->getLatestAssignmentForType($assignments, $type);
            }

            $popupAssignment = $assignments->first(fn (FormAssignment $assignment) => $this->isOpenedPendingStartAssignment($assignment));
        }

        return view('applicant.tests.index', [
            'candidate' => $candidate,
            'types' => $assignableTypes,
            'assignmentsByType' => $assignmentsByType,
            'popupAssignment' => $popupAssignment,
            'showPendingStartPopup' => $popupAssignment !== null,
            'popupStartUrl' => $popupAssignment ? route('applicant.tests.show', $popupAssignment) : null,
        ]);
    }

    public function show(Request $request, FormAssignment $assignment)
    {
        $candidate = $this->getCandidateOrAbort($request, $assignment);

        $assignment->load(['form.questions.options', 'attempt']);
        $this->expireIfNeeded($assignment);
        $assignment->refresh()->load(['form.questions.options', 'attempt']);

        $attempt = $assignment->attempt;
        $endsAt = $this->resolveEndsAt($assignment, $attempt);
        $remainingSeconds = $this->remainingSeconds($endsAt);
        $canStart = $assignment->status === FormAssignment::STATUS_OPENED
            && (! $attempt || ! $attempt->started_at);

        return view('applicant.tests.show', [
            'candidate' => $candidate,
            'assignment' => $assignment,
            'remainingSeconds' => $remainingSeconds,
            'canStart' => $canStart,
            'endsAt' => $endsAt,
            'serverNow' => now(),
        ]);
    }

    public function start(Request $request, FormAssignment $assignment)
    {
        $this->getCandidateOrAbort($request, $assignment);

        $assignment->load(['form', 'attempt']);
        $this->expireIfNeeded($assignment);
        $assignment->refresh()->load(['form', 'attempt']);

        if ($assignment->status !== FormAssignment::STATUS_OPENED) {
            return response()->json(['message' => 'Test tidak tersedia untuk dimulai.'], 422);
        }

        $durationMinutes = (int) ($assignment->form->duration_minutes ?? 0);
        if ($durationMinutes <= 0) {
            return response()->json(['message' => 'Durasi test belum diatur. Hubungi HRD.'], 422);
        }

        $payload = DB::transaction(function () use ($assignment, $request, $durationMinutes): array {
            $lockedAssignment = FormAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAssignment->status !== FormAssignment::STATUS_OPENED) {
                throw ValidationException::withMessages(['assignment' => 'Test tidak tersedia untuk dimulai.']);
            }

            $attempt = FormAttempt::query()
                ->where('form_assignment_id', $lockedAssignment->id)
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                $attempt = FormAttempt::create([
                    'form_assignment_id' => $lockedAssignment->id,
                    'started_at' => now(),
                ]);
            }

            if (! $attempt->started_at) {
                $attempt->update(['started_at' => now()]);
                $attempt->refresh();
            }

            $startedAt = $attempt->started_at;
            $endsAt = $startedAt->copy()->addMinutes($durationMinutes);

            $lockedAssignment->update([
                'opened_at' => $lockedAssignment->opened_at ?: now(),
                'expires_at' => $endsAt,
                'created_by' => $lockedAssignment->created_by ?: $request->user()->id,
            ]);

            return [
                'started_at' => $startedAt,
                'ends_at' => $endsAt,
            ];
        });

        return response()->json([
            'started_at' => $payload['started_at']->toIso8601String(),
            'ends_at' => $payload['ends_at']->toIso8601String(),
            'server_now' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request, FormAssignment $assignment, FormScoringService $scoringService)
    {
        $candidate = $this->getCandidateOrAbort($request, $assignment);
        $assignment->load(['form.questions.options', 'attempt']);
        $this->expireIfNeeded($assignment);
        $assignment->refresh()->load(['form.questions.options', 'attempt']);

        if ($assignment->status !== FormAssignment::STATUS_OPENED) {
            return redirect()->route('applicant.tests.show', $assignment)->with('error', 'Test tidak tersedia untuk dikerjakan.');
        }

        $form = $assignment->form;
        $questions = $form->questions;
        $normalized = $this->validateAndNormalizeAnswers($request, $questions, (string) $form->type);

        try {
            DB::transaction(function () use ($assignment, $questions, $normalized, $scoringService, $form, $candidate): void {
                $lockedAssignment = FormAssignment::query()
                    ->whereKey($assignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $attempt = FormAttempt::query()
                    ->where('form_assignment_id', $lockedAssignment->id)
                    ->lockForUpdate()
                    ->first();

                if (! $attempt || ! $attempt->started_at) {
                    throw ValidationException::withMessages(['start' => 'Klik Mulai Test terlebih dahulu.']);
                }

                $durationMinutes = (int) ($form->duration_minutes ?? 0);
                if ($durationMinutes <= 0) {
                    throw ValidationException::withMessages(['duration' => 'Durasi test belum diatur. Hubungi HRD.']);
                }

                $endsAt = $attempt->started_at->copy()->addMinutes($durationMinutes);

                if (now()->greaterThan($endsAt)) {
                    $lockedAssignment->update([
                        'status' => FormAssignment::STATUS_EXPIRED,
                        'closed_at' => now(),
                        'expires_at' => $endsAt,
                    ]);

                    throw ValidationException::withMessages(['expired' => 'Waktu test sudah habis.']);
                }

                FormAnswer::query()->where('form_attempt_id', $attempt->id)->delete();

                foreach ($questions as $question) {
                    $key = 'q_' . $question->id;
                    $entry = $normalized[$key] ?? null;
                    if (! $entry) {
                        continue;
                    }

                    FormAnswer::create([
                        'form_attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'answer_text' => $entry['answer_text'],
                        'answer_value' => $entry['answer_value'],
                        'answer_json' => $entry['answer_json'],
                    ]);
                }

                $scoringPayload = array_map(fn ($item) => $item['raw'], $normalized);
                $computed = $scoringService->compute($form, $questions, $scoringPayload);
                $submittedAt = now();

                $attempt->update([
                    'submitted_at' => $submittedAt,
                    'time_spent_seconds' => max(0, $attempt->started_at->diffInSeconds($submittedAt)),
                    'computed_result' => $computed,
                ]);

                $lockedAssignment->update([
                    'status' => FormAssignment::STATUS_SUBMITTED,
                    'closed_at' => $submittedAt,
                    'expires_at' => $endsAt,
                ]);

                if ($form->type === AssessmentForm::TYPE_IQ || $form->type === AssessmentForm::TYPE_DISC) {
                    $assessment = CandidateAssessment::firstOrCreate(
                        ['candidate_id' => $candidate->id],
                        ['status' => CandidateAssessment::STATUS_IN_PROCESS]
                    );

                    if ($form->type === AssessmentForm::TYPE_IQ) {
                        $assessment->iq_score = (int) ($computed['score'] ?? $computed['iq_score'] ?? 0);
                    }

                    if ($form->type === AssessmentForm::TYPE_DISC) {
                        $assessment->disc_result = [
                            'D' => (int) data_get($computed, 'disc_axis.D', 0),
                            'I' => (int) data_get($computed, 'disc_axis.I', 0),
                            'S' => (int) data_get($computed, 'disc_axis.S', 0),
                            'C' => (int) data_get($computed, 'disc_axis.C', 0),
                            'most' => data_get($computed, 'disc_most_axis', []),
                            'least' => data_get($computed, 'disc_least_axis', []),
                            'legacy' => data_get($computed, 'disc_legacy_axis', []),
                            'graphs' => data_get($computed, 'graphs'),
                            'summary' => data_get($computed, 'summary'),
                        ];
                    }

                    $assessment->save();
                }
            });
        } catch (ValidationException $exception) {
            return redirect()
                ->route('applicant.tests.show', $assignment)
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Gagal mengirim test.');
        }

        return redirect()->route('applicant.tests.index')->with('success', 'Test berhasil dikirim.');
    }

    private function getCandidateOrAbort(Request $request, FormAssignment $assignment): Candidate
    {
        $user = $request->user();
        $candidate = $user ? $user->resolveCandidate() : null;
        abort_if(! $candidate || $assignment->candidate_id !== $candidate->id, 403);
        return $candidate;
    }

    private function expireIfNeeded(FormAssignment $assignment): void
    {
        if ($assignment->status !== FormAssignment::STATUS_OPENED) {
            return;
        }

        $assignment->loadMissing(['attempt', 'form']);
        $endsAt = $this->resolveEndsAt($assignment, $assignment->attempt);

        if (! $endsAt) {
            return;
        }

        if (now()->greaterThan($endsAt)) {
            $assignment->update([
                'status' => FormAssignment::STATUS_EXPIRED,
                'closed_at' => now(),
                'expires_at' => $endsAt,
            ]);
        } elseif (! $assignment->expires_at || ! $assignment->expires_at->equalTo($endsAt)) {
            $assignment->update(['expires_at' => $endsAt]);
        }
    }

    private function resolveEndsAt(FormAssignment $assignment, ?FormAttempt $attempt): ?Carbon
    {
        if (! $attempt || ! $attempt->started_at) {
            return null;
        }

        $durationMinutes = (int) ($assignment->form->duration_minutes ?? 0);
        if ($durationMinutes <= 0) {
            return null;
        }

        return $attempt->started_at->copy()->addMinutes($durationMinutes);
    }

    private function remainingSeconds(?Carbon $endsAt): ?int
    {
        if (! $endsAt) {
            return null;
        }

        return max(0, now()->diffInSeconds($endsAt, false));
    }

    private function isOpenedPendingStartAssignment(FormAssignment $assignment): bool
    {
        if ($assignment->status !== FormAssignment::STATUS_OPENED) {
            return false;
        }

        if ($assignment->attempt && $assignment->attempt->started_at) {
            return false;
        }

        return ! $assignment->expires_at || now()->lessThanOrEqualTo($assignment->expires_at);
    }

    private function getLatestAssignmentForType($assignments, string $type): ?FormAssignment
    {
        $items = $assignments
            ->filter(fn (FormAssignment $assignment) => $assignment->form && $assignment->form->type === $type)
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return $items->sortBy([
            fn (FormAssignment $assignment) => $this->statusPriority($assignment->status),
            fn (FormAssignment $assignment) => -($assignment->opened_at?->timestamp ?? $assignment->created_at?->timestamp ?? 0),
            fn (FormAssignment $assignment) => -((int) $assignment->id),
        ])->first();
    }

    private function statusPriority(string $status): int
    {
        return match ($status) {
            FormAssignment::STATUS_OPENED => 0,
            FormAssignment::STATUS_SUBMITTED => 1,
            FormAssignment::STATUS_EXPIRED => 2,
            FormAssignment::STATUS_LOCKED => 3,
            default => 9,
        };
    }

    private function validateAndNormalizeAnswers(Request $request, $questions, string $formType): array
    {
        $normalized = [];

        foreach ($questions as $question) {
            $key = 'q_' . $question->id;
            $input = $request->input($key);
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
                    'raw' => ['most' => $mostId, 'least' => $leastId],
                ];
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
}
