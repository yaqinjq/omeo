<?php

namespace App\Services\Sport;

use App\Models\Employee;
use App\Models\Sport\Attendance;
use App\Models\Sport\ImportBatch;
use App\Models\Sport\ImportRow;
use App\Models\Sport\NonMkoAttendance;
use App\Models\Sport\Participant;
use App\Models\Sport\Registration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import historis Excel/CSV untuk 1 event tujuan (lihat Spesifikasi v1.1
 * Section 9). Prinsip yang DIJAGA di sini, jangan dilonggarkan tanpa
 * keputusan owner baru:
 * - TIDAK ADA pencocokan berbasis nama depan saja. Identitas MKO dicocokkan
 *   HANYA lewat employee_number / nik / external_id (kolom stabil).
 * - Baris yang diklasifikasi Non-MKO HANYA lewat sinyal eksplisit dari
 *   kolom mapping (bukan fallback otomatis saat employee tidak ketemu) —
 *   supaya typo ID karyawan tidak diam-diam berubah jadi peserta luar baru.
 * - Commit idempotent: baris yang sudah match attendance yang sudah ada
 *   ditandai 'duplicate' dan DILEWATI saat commit, tidak dibuat dobel.
 * - Import TIDAK PERNAH menyentuh sport_reward_configs atau mengubah status
 *   apa pun di luar attendance/registration/participant.
 */
class ImportService
{
    public function parseAndStoreRows(ImportBatch $batch, UploadedFile $file): void
    {
        $rows = $this->extractRows($file);
        if ($rows === []) {
            return;
        }

        $headers = array_shift($rows);
        $headers = $this->normalizeHeaders($headers);

        $batchRows = [];
        $now = now();
        foreach ($rows as $i => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $assoc = [];
            foreach ($headers as $colIndex => $header) {
                $assoc[$header] = $row[$colIndex] ?? null;
            }
            $batchRows[] = [
                'sport_import_batch_id' => $batch->id,
                'row_number' => $i + 2, // +1 header, +1 for 1-based display
                'raw_data' => json_encode($assoc),
                'row_status' => ImportRow::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ImportRow::insert($batchRows);
        $batch->update(['total_rows' => count($batchRows)]);
    }

    public function availableHeaders(ImportBatch $batch): array
    {
        $firstRow = $batch->rows()->first();
        if (! $firstRow) {
            return [];
        }

        return array_keys($firstRow->raw_data);
    }

    /**
     * Jalankan pencocokan untuk semua baris berdasarkan column_mapping yang
     * sudah disimpan. Aman dijalankan berulang sebelum commit (tidak
     * mengubah data di luar sport_import_rows/sport_import_batches).
     */
    public function runPreview(ImportBatch $batch): void
    {
        $mapping = $batch->column_mapping ?? [];
        $identifierCol = $mapping['identifier_column'] ?? null;
        $nameCol = $mapping['name_column'] ?? null;
        $typeCol = $mapping['participant_type_column'] ?? null;
        $attendedAtCol = $mapping['attended_at_column'] ?? null;

        $counts = ['valid' => 0, 'duplicate' => 0, 'invalid' => 0, 'unmatched' => 0];

        foreach ($batch->rows as $row) {
            $raw = $row->raw_data;
            $identifier = $identifierCol ? trim((string) ($raw[$identifierCol] ?? '')) : '';
            $name = $nameCol ? trim((string) ($raw[$nameCol] ?? '')) : '';
            $typeSignal = $typeCol ? strtolower(trim((string) ($raw[$typeCol] ?? ''))) : '';
            $isExplicitNonMko = in_array($typeSignal, ['non_mko', 'non-mko', 'non mko', 'luar', 'external'], true);

            $update = [
                'participant_type' => null,
                'matched_employee_id' => null,
                'matched_participant_id' => null,
                'row_status' => ImportRow::STATUS_INVALID,
                'error_message' => null,
            ];

            if ($identifier === '' && $name === '') {
                $update['error_message'] = 'Identifier dan nama kosong.';
                $row->update($update);
                $counts['invalid']++;
                continue;
            }

            $attendedAt = $this->parseAttendedAt($attendedAtCol ? ($raw[$attendedAtCol] ?? null) : null, $batch->event);

            if ($isExplicitNonMko) {
                $update['participant_type'] = ImportRow::PARTICIPANT_TYPE_NON_MKO;
                if ($name === '') {
                    $update['error_message'] = 'Baris Non-MKO wajib punya nama.';
                    $update['row_status'] = ImportRow::STATUS_INVALID;
                    $row->update($update);
                    $counts['invalid']++;
                    continue;
                }

                $participant = Participant::where('external_reference', $identifier)->first();
                $update['matched_participant_id'] = $participant?->id;

                $isDuplicate = $participant && NonMkoAttendance::where('sport_event_id', $batch->sport_event_id)
                    ->where('sport_participant_id', $participant->id)->exists();

                $update['row_status'] = $isDuplicate ? ImportRow::STATUS_DUPLICATE : ImportRow::STATUS_VALID;
                $row->update($update);
                $counts[$update['row_status']]++;
                continue;
            }

            if ($identifier === '') {
                $update['error_message'] = 'Kolom identifier kosong untuk baris MKO (tidak ada sinyal Non-MKO).';
                $row->update($update);
                $counts['invalid']++;
                continue;
            }

            $employee = Employee::where('employee_number', $identifier)
                ->orWhere('nik', $identifier)
                ->orWhere('external_id', $identifier)
                ->first();

            if (! $employee) {
                $update['participant_type'] = ImportRow::PARTICIPANT_TYPE_MKO;
                $update['row_status'] = ImportRow::STATUS_UNMATCHED;
                $update['error_message'] = "Identifier '{$identifier}' tidak cocok dengan employee_number/nik/external_id manapun.";
                $row->update($update);
                $counts['unmatched']++;
                continue;
            }

            $update['participant_type'] = ImportRow::PARTICIPANT_TYPE_MKO;
            $update['matched_employee_id'] = $employee->id;

            $linkedUser = User::where('employee_id', $employee->id)->first();
            if (! $linkedUser) {
                $update['row_status'] = ImportRow::STATUS_INVALID;
                $update['error_message'] = 'Karyawan ditemukan tetapi tidak punya akun User OMEO — tidak bisa diimport ke attendance tanpa akun (lihat README).';
                $row->update($update);
                $counts['invalid']++;
                continue;
            }

            $isDuplicate = Attendance::where('sport_event_id', $batch->sport_event_id)
                ->where('employee_id', $employee->id)->exists();

            $update['row_status'] = $isDuplicate ? ImportRow::STATUS_DUPLICATE : ImportRow::STATUS_VALID;
            $row->update($update);
            $counts[$update['row_status']]++;
        }

        $batch->update([
            'status' => ImportBatch::STATUS_PREVIEWED,
            'valid_rows' => $counts['valid'],
            'duplicate_rows' => $counts['duplicate'],
            'invalid_rows' => $counts['invalid'],
            'unmatched_rows' => $counts['unmatched'],
        ]);
    }

    /**
     * Commit hanya baris 'valid'. Baris 'duplicate' dilewati (tidak membuat
     * record dobel) tetap idempotent walau file yang sama di-commit 2x.
     */
    public function commit(ImportBatch $batch, int $actorUserId): void
    {
        DB::transaction(function () use ($batch, $actorUserId) {
            foreach ($batch->rows()->where('row_status', ImportRow::STATUS_VALID)->get() as $row) {
                $raw = $row->raw_data;
                $mapping = $batch->column_mapping ?? [];
                $attendedAtCol = $mapping['attended_at_column'] ?? null;
                $attendedAt = $this->parseAttendedAt($attendedAtCol ? ($raw[$attendedAtCol] ?? null) : null, $batch->event);

                if ($row->participant_type === ImportRow::PARTICIPANT_TYPE_NON_MKO) {
                    $identifierCol = $mapping['identifier_column'] ?? null;
                    $nameCol = $mapping['name_column'] ?? null;
                    $identifier = $identifierCol ? trim((string) ($raw[$identifierCol] ?? '')) : '';
                    $name = trim((string) ($raw[$nameCol] ?? ''));

                    $participant = $row->matched_participant_id
                        ? Participant::find($row->matched_participant_id)
                        : Participant::create([
                            'full_name' => $name,
                            'external_reference' => $identifier ?: null,
                            'created_by_user_id' => $actorUserId,
                        ]);

                    $attendance = NonMkoAttendance::updateOrCreate(
                        ['sport_event_id' => $batch->sport_event_id, 'sport_participant_id' => $participant->id],
                        ['recorded_by_user_id' => $actorUserId, 'attended_at' => $attendedAt, 'notes' => 'Import historis batch #' . $batch->id]
                    );

                    $row->update(['matched_participant_id' => $participant->id, 'resulting_non_mko_attendance_id' => $attendance->id]);

                    continue;
                }

                $employee = Employee::find($row->matched_employee_id);
                $user = User::where('employee_id', $employee->id)->first();

                $registration = Registration::firstOrCreate(
                    ['sport_event_id' => $batch->sport_event_id, 'employee_id' => $employee->id],
                    ['user_id' => $user->id, 'status' => Registration::STATUS_REGISTERED, 'registered_at' => $attendedAt]
                );

                $attendance = Attendance::updateOrCreate(
                    ['sport_event_id' => $batch->sport_event_id, 'employee_id' => $employee->id],
                    ['sport_registration_id' => $registration->id, 'confirmed_at' => $attendedAt]
                );

                $row->update(['resulting_attendance_id' => $attendance->id]);
            }

            $batch->update([
                'status' => ImportBatch::STATUS_COMMITTED,
                'committed_at' => now(),
                'committed_by_user_id' => $actorUserId,
            ]);
        });
    }

    private function parseAttendedAt(?string $raw, $event): Carbon
    {
        if ($raw) {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // fall through to default below
            }
        }

        return $event->end_at->copy();
    }

    private function extractRows(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'csv' || $ext === 'txt') {
            $rows = [];
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle !== false) {
                while (($line = fgetcsv($handle)) !== false) {
                    $rows[] = $line;
                }
                fclose($handle);
            }

            return $rows;
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    }

    private function normalizeHeaders(array $headers): array
    {
        $seen = [];
        $normalized = [];
        foreach ($headers as $i => $header) {
            $label = trim((string) $header) !== '' ? trim((string) $header) : "Kolom" . ($i + 1);
            if (isset($seen[$label])) {
                $seen[$label]++;
                $label .= '_' . $seen[$label];
            } else {
                $seen[$label] = 1;
            }
            $normalized[$i] = $label;
        }

        return $normalized;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
