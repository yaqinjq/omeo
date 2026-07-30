<?php

namespace App\Jobs;

use App\Models\EmployeeImportSession;
use App\Services\HR\EmployeeImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessEmployeeImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        public readonly int $sessionId,
        public readonly int $importedBy,
    ) {}

    public function handle(EmployeeImportService $service): void
    {
        $session = EmployeeImportSession::find($this->sessionId);
        if (!$session) {
            return;
        }

        try {
            set_time_limit(600);
            ini_set('memory_limit', '512M');

            $parsedRows = $session->parsed_rows;

            if (empty($parsedRows)) {
                $session->update([
                    'status'        => 'failed',
                    'error_message' => 'Data karyawan tidak ditemukan di sesi ini.',
                ]);
                return;
            }

            $stats = $service->import($parsedRows, $this->importedBy);

            $session->update([
                'status'                => 'completed',
                'new_count'             => $stats['new'],
                'updated_count'         => $stats['updated'],
                'skipped_count'         => $stats['skipped'],
                'account_created_count' => $stats['accounts_created'],
                'error_message'         => !empty($stats['errors'])
                    ? json_encode($stats['errors'])
                    : null,
            ]);

            // Hapus file sumber setelah import selesai
            if ($session->source_file_path) {
                Storage::disk('local')->delete($session->source_file_path);
                $session->update(['source_file_path' => null]);
            }

        } catch (\Throwable $e) {
            Log::error('ProcessEmployeeImport job failed', [
                'session_id' => $this->sessionId,
                'error'      => $e->getMessage(),
            ]);
            $session->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        EmployeeImportSession::where('id', $this->sessionId)
            ->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
    }
}
