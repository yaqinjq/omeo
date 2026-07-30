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

class DiscQuestionImportService
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
            [
                'Saat bekerja dalam tim, saya cenderung...',
                'radio',
                'Wajib',
                'Langsung mengambil keputusan',
                'Menyemangati tim',
                'Menjaga stabilitas tim',
                'Memastikan prosedur benar',
                '',
                'D',
                'I',
                'S',
                'C',
                '',
                'C',
                'S',
                'I',
                'D',
                '',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{form:AssessmentForm,imported_questions:int,imported_options:int,skipped_rows:int,row_errors:array<int,array{row:int,message:string}>,warnings:array<int,string>}
     */
    public function import(UploadedFile $file, array $payload, int $userId): array
    {
        $rows = $this->readRows($file);
        $match = $this->matchHeaders($rows);
        $parsed = $this->parseRows($rows, $match);
        $validRows = $parsed['valid_rows'];
        $rowErrors = $parsed['row_errors'];

        if (($payload['target_mode'] ?? null) === 'create' && count($validRows) === 0) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada baris valid untuk diimport. Periksa format data pada file template DISC.',
            ]);
        }

        $form = DB::transaction(function () use ($payload, $userId, $validRows): AssessmentForm {
            if ($payload['target_mode'] === 'create') {
                $form = AssessmentForm::create([
                    'code' => 'FORM-' . Str::upper(Str::random(8)),
                    'name' => trim((string) $payload['form_name']),
                    'description' => 'Diimport dari template bank soal DISC dual-axis',
                    'type' => AssessmentForm::TYPE_DISC,
                    'duration_minutes' => (int) $payload['duration_minutes'],
                    'is_active' => false,
                    'created_by' => $userId,
                ]);
            } else {
                /** @var AssessmentForm $form */
                $form = AssessmentForm::query()
                    ->whereKey((int) $payload['form_id'])
                    ->where('type', AssessmentForm::TYPE_DISC)
                    ->firstOrFail();
            }

            $nextPosition = ((int) $form->questions()->max('position')) + 1;

            foreach ($validRows as $entry) {
                $question = $form->questions()->create([
                    'position' => $nextPosition,
                    'question_text' => $entry['question_text'],
                    'question_type' => $entry['question_type'],
                    'is_required' => $entry['is_required'],
                    'settings' => ['disc_mode' => 'dual_axis'],
                ]);

                foreach ($entry['options'] as $optionPosition => $optionData) {
                    $question->options()->create([
                        'position' => $optionPosition + 1,
                        'option_text' => $optionData['option_text'],
                        'value' => null,
                        'weight' => null,
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
        $columns = [
            ImportTemplateColumn::make('question_text', true, ['tulis pertanyaan'], ['id' => 'Tulis Pertanyaan']),
            ImportTemplateColumn::make('question_type', true, ['tipe jawaban'], ['id' => 'Tipe jawaban']),
            ImportTemplateColumn::make('is_required', true, ['wajib diisi'], ['id' => 'Wajib diisi']),
        ];

        foreach (range(1, 5) as $index) {
            $columns[] = ImportTemplateColumn::make('option_' . $index, false, ['opsi ' . $index], ['id' => 'Opsi ' . $index]);
            $columns[] = ImportTemplateColumn::make('most_axis_' . $index, false, ['axis most ' . $index, 'axis_' . $index, 'sumbu ' . $index], ['id' => 'Axis Most ' . $index]);
            $columns[] = ImportTemplateColumn::make('least_axis_' . $index, false, ['axis least ' . $index], ['id' => 'Axis Least ' . $index]);
        }

        return ImportTemplateSchema::make($columns);
    }

    private function matchHeaders(array $rows): \App\Support\ImportHeaderMatchResult
    {
        $match = $this->headerValidator->match($rows[0] ?? [], $this->schema());
        $this->headerValidator->ensureValid($match, 'DISC');

        return $match;
    }

    /**
     * @param array<int,array<int,mixed>> $rows
     * @param \App\Support\ImportHeaderMatchResult $match
     * @return array{valid_rows:array<int,array<string,mixed>>,row_errors:array<int,array{row:int,message:string}>}
     */
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

        return [
            'valid_rows' => $validRows,
            'row_errors' => $rowErrors,
        ];
    }

    /**
     * @param array<int,string> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,string> $row
     */
    private function validateRow(array $row): ?string
    {
        $questionText = trim((string) ($row['question_text'] ?? ''));
        if ($questionText === '') {
            return 'Kolom question_text wajib diisi.';
        }

        $questionType = strtolower(trim((string) ($row['question_type'] ?? '')));
        if (! in_array($questionType, [FormQuestion::TYPE_RADIO, FormQuestion::TYPE_DROPDOWN], true)) {
            return 'question_type untuk DISC hanya boleh radio atau dropdown.';
        }

        $options = $this->collectOptions($row);
        if (count($options) < 2) {
            return 'Minimal 2 opsi harus diisi untuk setiap pertanyaan DISC.';
        }

        foreach (range(1, 5) as $index) {
            $optionText = trim((string) ($row['option_' . $index] ?? ''));
            if ($optionText === '') {
                continue;
            }

            $mostAxis = strtoupper(trim((string) ($row['most_axis_' . $index] ?? '')));
            $leastAxis = strtoupper(trim((string) ($row['least_axis_' . $index] ?? '')));

            if (! in_array($mostAxis, ['D', 'I', 'S', 'C'], true)) {
                return "most_axis_{$index} wajib diisi D/I/S/C untuk opsi yang terisi.";
            }

            if ($leastAxis !== '' && ! in_array($leastAxis, ['D', 'I', 'S', 'C'], true)) {
                return "least_axis_{$index} wajib diisi D/I/S/C atau dikosongkan.";
            }
        }

        return null;
    }

    /**
     * @param array<string,string> $row
     * @return array<string,mixed>
     */
    private function buildPayload(array $row): array
    {
        $options = [];

        foreach (range(1, 5) as $idx) {
            $optionText = trim((string) ($row['option_' . $idx] ?? ''));
            if ($optionText === '') {
                continue;
            }

            $mostAxis = strtoupper(trim((string) ($row['most_axis_' . $idx] ?? '')));
            $leastAxis = strtoupper(trim((string) ($row['least_axis_' . $idx] ?? '')));
            if ($leastAxis === '') {
                $leastAxis = $mostAxis;
            }

            $options[] = [
                'option_text' => $optionText,
                'meta' => [
                    'disc_axis' => $mostAxis,
                    'disc_axis_most' => $mostAxis,
                    'disc_axis_least' => $leastAxis,
                ],
            ];
        }

        return [
            'question_text' => trim((string) $row['question_text']),
            'question_type' => strtolower(trim((string) $row['question_type'])),
            'is_required' => $this->toBoolean($row['is_required'] ?? '0'),
            'options' => $options,
        ];
    }

    /**
     * @param array<string,string> $row
     * @return array<int,string>
     */
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

    /**
     * @return array<int,array<int,mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $sheets = Excel::toArray([], $file);
        $rows = $sheets[0] ?? [];

        if (! is_array($rows) || count($rows) === 0) {
            throw ValidationException::withMessages([
                'file' => 'File tidak berisi data yang dapat diimport.',
            ]);
        }

        return $rows;
    }
}
