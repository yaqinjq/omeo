<?php

namespace App\Services;

use App\Models\AssessmentForm;
use App\Models\FormQuestion;
use App\Support\ImportHeaderValidator;
use App\Support\ImportTemplateColumn;
use App\Support\ImportTemplateSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class IqQuestionImportService
{
    public function __construct(private readonly ImportHeaderValidator $headerValidator)
    {
    }

    /**
     * @return array<int,string>
     */
    public function templateHeaders(): array
    {
        return $this->schema()->exportHeaders();
    }

    /**
     * @return array<int,string>
     */
    public function templateHeadersIndonesia(): array
    {
        return $this->schema()->exportHeaders('id');
    }

    /**
     * @return array<int,array<int,string|int|null>>
     */
    public function templateSampleRows(): array
    {
        return [
            ['Jika 2 + 3 = ?', 'radio', 'Wajib', '4', '5', '6', '', '', 0, 10, 0, '', '', 2],
            ['Sebutkan ibu kota Indonesia.', 'short_text', 'Tidak Wajib', '', '', '', '', '', '', '', '', '', '', ''],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{form:AssessmentForm,imported_questions:int,imported_options:int,skipped_rows:int,row_errors:array<int,array{row:int,message:string}>,warnings:array<int,string>}
     */
    public function import(UploadedFile $file, array $payload, int $userId, string $formType = AssessmentForm::TYPE_IQ): array
    {
        if (!AssessmentForm::isObjectiveScoreType($formType)) {
            throw ValidationException::withMessages([
                'file' => 'Tipe form tidak didukung oleh importer pilihan berbobot.',
            ]);
        }

        $rows = $this->readRows($file);
        $match = $this->matchHeaders($rows, 'soal');

        $parsed = $this->parseRows($rows, $match);
        $validRows = $parsed['valid_rows'];
        $rowErrors = $parsed['row_errors'];

        if (($payload['target_mode'] ?? null) === 'create' && count($validRows) === 0) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada baris valid untuk diimport. Periksa format data pada file template.',
            ]);
        }

        $form = DB::transaction(function () use ($payload, $userId, $validRows, $formType): AssessmentForm {
            if ($payload['target_mode'] === 'create') {
                $form = AssessmentForm::create([
                    'code' => 'FORM-' . Str::upper(Str::random(8)),
                    'name' => trim((string) $payload['form_name']),
                    'description' => 'Diimport dari template bank soal ' . AssessmentForm::labelFor($formType),
                    'type' => $formType,
                    'duration_minutes' => (int) $payload['duration_minutes'],
                    'is_active' => false,
                    'created_by' => $userId,
                ]);
            } else {
                /** @var AssessmentForm $form */
                $form = AssessmentForm::query()
                    ->whereKey((int) $payload['form_id'])
                    ->where('type', $formType)
                    ->firstOrFail();
            }

            $nextPosition = ((int) $form->questions()->max('position')) + 1;

            foreach ($validRows as $entry) {
                $question = $form->questions()->create([
                    'position' => $nextPosition,
                    'question_text' => $entry['question_text'],
                    'question_type' => $entry['question_type'],
                    'is_required' => $entry['is_required'],
                    'settings' => $entry['settings'],
                ]);

                foreach ($entry['options'] as $optionPosition => $optionData) {
                    $question->options()->create([
                        'position' => $optionPosition + 1,
                        'option_text' => $optionData['option_text'],
                        'value' => $optionData['value'],
                        'weight' => $optionData['weight'],
                        'meta' => $optionData['meta'],
                    ]);
                }

                $nextPosition++;
            }

            return $form;
        });

        $importedOptions = array_sum(array_map(static fn ($entry) => count($entry['options']), $validRows));

        return [
            'form' => $form,
            'imported_questions' => count($validRows),
            'imported_options' => $importedOptions,
            'skipped_rows' => count($rowErrors),
            'row_errors' => $rowErrors,
            'warnings' => $match->warnings(),
        ];
    }

    private function schema(): ImportTemplateSchema
    {
        return ImportTemplateSchema::make([
            ImportTemplateColumn::make('question_text', true, ['tulis pertanyaan'], ['id' => 'Tulis Pertanyaan']),
            ImportTemplateColumn::make('question_type', true, ['tipe jawaban'], ['id' => 'Tipe jawaban']),
            ImportTemplateColumn::make('is_required', true, ['wajib diisi'], ['id' => 'Wajib diisi']),
            ImportTemplateColumn::make('option_1', false, [], ['id' => 'option_1']),
            ImportTemplateColumn::make('option_2', false, [], ['id' => 'option_2']),
            ImportTemplateColumn::make('option_3', false, [], ['id' => 'option_3']),
            ImportTemplateColumn::make('option_4', false, [], ['id' => 'option_4']),
            ImportTemplateColumn::make('option_5', false, [], ['id' => 'option_5']),
            ImportTemplateColumn::make('weight_1', false, [], ['id' => 'weight_1']),
            ImportTemplateColumn::make('weight_2', false, [], ['id' => 'weight_2']),
            ImportTemplateColumn::make('weight_3', false, [], ['id' => 'weight_3']),
            ImportTemplateColumn::make('weight_4', false, [], ['id' => 'weight_4']),
            ImportTemplateColumn::make('weight_5', false, [], ['id' => 'weight_5']),
            ImportTemplateColumn::make('correct_index', false, [], ['id' => 'correct_index']),
        ]);
    }

    private function matchHeaders(array $rows, string $moduleLabel): \App\Support\ImportHeaderMatchResult
    {
        $match = $this->headerValidator->match($rows[0] ?? [], $this->schema());
        $this->headerValidator->ensureValid($match, $moduleLabel);

        return $match;
    }

    /** @param array<int,array<int,mixed>> $rows @param \App\Support\ImportHeaderMatchResult $match @return array{valid_rows:array<int,array<string,mixed>>,row_errors:array<int,array{row:int,message:string}>} */
    private function parseRows(array $rows, \App\Support\ImportHeaderMatchResult $match): array
    {
        $validRows = [];
        $rowErrors = [];

        foreach (array_slice($rows, 1) as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $match->mapRow($rawRow);

            if ($this->isEmptyRow(array_values($row))) {
                continue;
            }

            $error = $this->validateRow($row);
            if ($error !== null) {
                $rowErrors[] = ['row' => $rowNumber, 'message' => $error];
                continue;
            }

            $validRows[] = $this->buildPayload($row);
        }

        return ['valid_rows' => $validRows, 'row_errors' => $rowErrors];
    }

    /** @param array<int,string> $row */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,string> $row */
    private function validateRow(array $row): ?string
    {
        $questionText = trim((string) ($row['question_text'] ?? ''));
        if ($questionText === '') {
            return 'Kolom question_text wajib diisi.';
        }

        $questionType = strtolower(trim((string) ($row['question_type'] ?? '')));
        $allowedTypes = [
            FormQuestion::TYPE_RADIO,
            FormQuestion::TYPE_DROPDOWN,
            FormQuestion::TYPE_CHECKBOX,
            FormQuestion::TYPE_SHORT_TEXT,
            FormQuestion::TYPE_PARAGRAPH,
            FormQuestion::TYPE_RATING,
            FormQuestion::TYPE_LINEAR_SCALE,
        ];

        if (! in_array($questionType, $allowedTypes, true)) {
            return 'question_type tidak valid. Gunakan: radio, dropdown, checkbox, short_text, paragraph, rating, linear_scale.';
        }

        $isChoice = in_array($questionType, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN, FormQuestion::TYPE_CHECKBOX], true);
        $options = $this->collectOptions($row);

        if ($isChoice && count($options) < 2) {
            return 'Tipe pertanyaan pilihan wajib memiliki minimal 2 opsi terisi.';
        }

        if (in_array($questionType, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN], true)) {
            $correctIndex = trim((string) ($row['correct_index'] ?? ''));
            if ($correctIndex !== '') {
                if (! ctype_digit($correctIndex)) {
                    return 'correct_index harus berupa angka 1 sampai 5.';
                }

                $correct = (int) $correctIndex;
                if ($correct < 1 || $correct > 5) {
                    return 'correct_index harus di rentang 1 sampai 5.';
                }

                $optionText = trim((string) ($row['option_' . $correct] ?? ''));
                if ($optionText === '') {
                    return 'correct_index menunjuk opsi yang kosong.';
                }
            }
        }

        foreach (range(1, 5) as $index) {
            $weight = trim((string) ($row['weight_' . $index] ?? ''));
            if ($weight !== '' && ! is_numeric($weight)) {
                return "weight_{$index} harus angka atau kosong.";
            }
        }

        return null;
    }

    /** @param array<string,string> $row @return array<string,mixed> */
    private function buildPayload(array $row): array
    {
        $questionType = strtolower(trim((string) $row['question_type']));
        $isRequired = $this->toBoolean($row['is_required'] ?? '0');

        $settings = null;
        if (in_array($questionType, [FormQuestion::TYPE_RATING, FormQuestion::TYPE_LINEAR_SCALE], true)) {
            $settings = ['min' => 1, 'max' => 5, 'min_label' => null, 'max_label' => null];
        }

        $options = [];
        $isChoice = in_array($questionType, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN, FormQuestion::TYPE_CHECKBOX], true);

        if ($isChoice) {
            $correctIndex = trim((string) ($row['correct_index'] ?? ''));
            $correct = $correctIndex !== '' ? (int) $correctIndex : null;

            foreach (range(1, 5) as $idx) {
                $optionText = trim((string) ($row['option_' . $idx] ?? ''));
                if ($optionText === '') {
                    continue;
                }

                $weightRaw = trim((string) ($row['weight_' . $idx] ?? ''));
                $weight = $weightRaw === '' ? 0 : (int) $weightRaw;

                $meta = null;
                if (in_array($questionType, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN], true) && $correct !== null) {
                    $meta = ['is_correct' => $idx === $correct];
                }

                $options[] = [
                    'option_text' => $optionText,
                    'value' => null,
                    'weight' => $weight,
                    'meta' => $meta,
                ];
            }
        }

        return [
            'question_text' => trim((string) $row['question_text']),
            'question_type' => $questionType,
            'is_required' => $isRequired,
            'settings' => $settings,
            'options' => $options,
        ];
    }

    /** @param array<string,string> $row @return array<int,string> */
    private function collectOptions(array $row): array
    {
        $items = [];
        foreach (range(1, 5) as $idx) {
            $value = trim((string) ($row['option_' . $idx] ?? ''));
            if ($value !== '') {
                $items[] = $value;
            }
        }

        return $items;
    }

    private function toBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'ya', 'yes', 'wajib'], true);
    }

    /** @return array<int,array<int,mixed>> */
    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray([], $file);
        $rows = $sheets[0] ?? [];

        if (! is_array($rows) || count($rows) === 0) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data yang dapat diimport.']);
        }

        return $rows;
    }
}
