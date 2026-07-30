<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPayrollRekeningCommand extends Command
{
    protected $signature   = 'payroll:fix-rekening {--dry-run : Tampilkan preview tanpa mengubah data}';
    protected $description = 'Recover no_rekening yang tersimpan sebagai scientific notation';

    public function handle(): int
    {
        // Hanya match scientific notation sungguhan: "1,41002E+12", "1.64e+12", dll.
        // Pola: diawali digit/koma/titik, lalu E/e, lalu opsional +/-, lalu digit
        $rows = DB::table('payroll_import_rows')
            ->where('no_rekening', 'REGEXP', '^[0-9,\\.]+[eE][+\\-]?[0-9]')
            ->get(['id', 'no_komp', 'nama', 'no_rekening', 'bank_name', 'session_id']);

        if ($rows->isEmpty()) {
            $this->info('Tidak ada no_rekening dengan format scientific notation.');
            return self::SUCCESS;
        }

        $this->info("Ditemukan {$rows->count()} baris dengan scientific notation:");
        $this->newLine();

        $fixable = 0;
        $broken  = 0;
        $updates = [];

        foreach ($rows as $row) {
            $raw        = $row->no_rekening;
            $normalized = str_replace(',', '.', $raw);
            $float      = (float) $normalized;
            $recovered  = number_format($float, 0, '', '');

            // Hitung digit signifikan yang tersimpan di scientific notation
            // Contoh "1,41002E+12" → significand = "141002" → 6 digit
            preg_match('/^([\d,\.]+)[eE]/', $normalized, $m);
            $significand       = str_replace([',', '.'], '', $m[1] ?? '');
            $significantDigits = strlen(ltrim($significand, '0'));
            $recoveredLength   = strlen($recovered);

            // Anggap recoverable jika:
            // - panjang recovered >= 10 digit (rekening bank minimal)
            // - digit signifikan >= panjang recovered - 3 (toleransi 3 trailing zero)
            $isRecoverable = $recoveredLength >= 10
                && $significantDigits >= ($recoveredLength - 3);

            $statusLabel = $isRecoverable ? '✅ RECOVERABLE' : '🔴 BROKEN';

            $this->line(sprintf(
                '  ID=%-5d | no_komp=%-6s | %-30s | %s → %s | %s',
                $row->id,
                $row->no_komp,
                mb_strimwidth($row->nama, 0, 30, '…'),
                $raw,
                $recovered,
                $statusLabel
            ));

            if ($isRecoverable) {
                $fixable++;
                $updates[] = ['id' => $row->id, 'no_rekening' => $recovered, 'flag' => 'recovered'];
            } else {
                $broken++;
                $updates[] = ['id' => $row->id, 'no_rekening' => 'RUSAK:' . $raw, 'flag' => 'broken'];
            }
        }

        $this->newLine();
        $this->info("Recoverable: {$fixable} | Broken (perlu input ulang): {$broken}");

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('[DRY RUN] Tidak ada data yang diubah. Jalankan tanpa --dry-run untuk apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $upd) {
                DB::table('payroll_import_rows')
                    ->where('id', $upd['id'])
                    ->update([
                        'no_rekening'  => $upd['no_rekening'],
                        'rekening_flag' => $upd['flag'],
                        'updated_at'   => now(),
                    ]);
            }
        });

        $this->info('Recovery selesai. ' . count($updates) . ' baris diperbarui.');

        return self::SUCCESS;
    }
}
