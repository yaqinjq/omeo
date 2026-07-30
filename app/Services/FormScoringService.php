<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\FormQuestion;
use Illuminate\Support\Collection;

class FormScoringService
{
    public function compute(AssessmentForm $form, Collection $questions, array $submitted): array
    {
        if (AssessmentForm::isObjectiveScoreType((string) $form->type)) {
            return $this->computeObjectiveScore($questions, $submitted, (string) $form->type);
        }

        if (AssessmentForm::isDiscType((string) $form->type)) {
            return $this->computeDisc($questions, $submitted);
        }

        return [];
    }

    private function computeObjectiveScore(Collection $questions, array $submitted, string $type): array
    {
        $score = 0;
        $details = [];

        foreach ($questions as $question) {
            $key = 'q_' . $question->id;
            $answer = $submitted[$key] ?? null;
            $point = 0;

            if (in_array($question->question_type, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN, FormQuestion::TYPE_RATING, FormQuestion::TYPE_LINEAR_SCALE], true)) {
                $selected = $question->options->firstWhere('id', (int) $answer);
                $point = (int) ($selected->weight ?? 0);
            } elseif ($question->question_type === FormQuestion::TYPE_CHECKBOX) {
                $selectedIds = is_array($answer) ? array_map('intval', $answer) : [];
                $point = (int) $question->options
                    ->whereIn('id', $selectedIds)
                    ->sum(fn ($option) => (int) ($option->weight ?? 0));
            }

            $score += $point;
            $details[] = ['question_id' => $question->id, 'score' => $point];
        }

        $payload = [
            'score' => $score,
            'details' => $details,
            'category' => $type,
        ];

        if ($type === AssessmentForm::TYPE_IQ) {
            $payload['iq_score'] = $score;
        }

        return $payload;
    }

    private function computeDisc(Collection $questions, array $submitted): array
    {
        $most = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
        $least = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
        $legacy = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
        $details = [];

        foreach ($questions as $question) {
            $key = 'q_' . $question->id;
            $answer = $submitted[$key] ?? null;

            if (is_array($answer) && (array_key_exists('most', $answer) || array_key_exists('least', $answer))) {
                $mostOption = $question->options->firstWhere('id', (int) ($answer['most'] ?? 0));
                $leastOption = $question->options->firstWhere('id', (int) ($answer['least'] ?? 0));

                $mostAxis = $this->resolveDiscAxis($mostOption, 'most');
                $leastAxis = $this->resolveDiscAxis($leastOption, 'least');

                $this->incrementDiscAxis($most, $mostAxis);
                $this->incrementDiscAxis($least, $leastAxis);

                $details[] = [
                    'question_id' => $question->id,
                    'most_option_id' => (int) ($answer['most'] ?? 0),
                    'least_option_id' => (int) ($answer['least'] ?? 0),
                    'most_axis' => $mostAxis,
                    'least_axis' => $leastAxis,
                ];
                continue;
            }

            if (in_array($question->question_type, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN], true)) {
                $option = $question->options->firstWhere('id', (int) $answer);
                $axis = $this->resolveDiscAxis($option, 'legacy');
                $this->incrementDiscAxis($legacy, $axis);
                $details[] = [
                    'question_id' => $question->id,
                    'selected_option_id' => (int) $answer,
                    'axis' => $axis,
                ];
            } elseif ($question->question_type === FormQuestion::TYPE_CHECKBOX) {
                $selectedIds = is_array($answer) ? array_map('intval', $answer) : [];
                foreach ($question->options->whereIn('id', $selectedIds) as $option) {
                    $this->incrementDiscAxis($legacy, $this->resolveDiscAxis($option, 'legacy'));
                }
            }
        }

        $delta = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
        foreach (array_keys($delta) as $axis) {
            $delta[$axis] = ($most[$axis] + $legacy[$axis]) - $least[$axis];
        }

        $dominantAxis = collect($delta)
            ->sortDesc()
            ->keys()
            ->first();

        return [
            'disc_axis' => $delta,
            'disc_most_axis' => $most,
            'disc_least_axis' => $least,
            'disc_legacy_axis' => $legacy,
            'details' => $details,
            'graphs' => [
                'most' => $most,
                'least' => $least,
                'delta' => $delta,
            ],
            'summary' => [
                'dominant_axis' => $dominantAxis,
                'dominant_label' => $this->discAxisLabel($dominantAxis),
            ],
        ];
    }

    private function resolveDiscAxis(mixed $option, string $mode): ?string
    {
        if (!$option) {
            return null;
        }

        $meta = is_array($option->meta ?? null) ? $option->meta : [];

        $axis = match ($mode) {
            'most' => $meta['disc_axis_most'] ?? $meta['disc_axis'] ?? null,
            'least' => $meta['disc_axis_least'] ?? $meta['disc_axis'] ?? null,
            default => $meta['disc_axis'] ?? $meta['disc_axis_most'] ?? null,
        };

        $axis = strtoupper(trim((string) $axis));

        return in_array($axis, ['D', 'I', 'S', 'C'], true) ? $axis : null;
    }

    private function incrementDiscAxis(array &$axis, mixed $discAxis): void
    {
        $key = strtoupper((string) $discAxis);
        if (array_key_exists($key, $axis)) {
            $axis[$key]++;
        }
    }

    private function discAxisLabel(?string $axis): string
    {
        return match ($axis) {
            'D' => 'Dominance',
            'I' => 'Influence',
            'S' => 'Steadiness',
            'C' => 'Compliance',
            default => 'Belum terdefinisi',
        };
    }
}
