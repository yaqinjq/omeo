<?php

namespace App\Http\Controllers\Sport\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sport\RewardConfig;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor Excel laporan Sport — filter & definisi metrik SAMA dengan
 * ReportController::index (dipakai ulang lewat instance yang sama, bukan
 * duplikasi logika). Lihat Spesifikasi v1.1 Section 8.
 */
class ReportExportController extends Controller
{
    public function __construct(private readonly ReportController $reportController)
    {
    }

    public function export(Request $request): BinaryFileResponse
    {
        // Hanya bersihkan output buffer yang (kalau ada) terbuka SELAMA
        // method ini berjalan — bukan seluruh stack buffer global. Loop
        // versi sebelumnya (while (ob_get_level()) { ob_end_clean(); })
        // menutup level 0, yang di PHPUnit berarti ikut menutup buffer
        // milik test runner sendiri (PHPUnit membungkus setiap test dengan
        // output buffering) → PHPUnit menandai test itu "risky" (buffer
        // level berkurang melebihi yang dibuka test). Method export() ini
        // tidak pernah merender view apa pun (murni query + PhpSpreadsheet),
        // jadi tidak ada stray output yang harus dibersihkan sampai level 0
        // — menutup hanya sampai level SAAT method ini dipanggil tetap
        // memberi perlindungan yang sama persis untuk kasus production
        // (stray echo dari helper/package lain), tanpa menyentuh buffer
        // yang sudah ada sebelum request ini. Tidak mengubah pola
        // AnnualSummaryExportController di modul Finance — perbaikan ini
        // hanya di controller Sport yang baru.
        $bufferLevelAtEntry = ob_get_level();
        while (ob_get_level() > $bufferLevelAtEntry) {
            ob_end_clean();
        }

        $filters = $this->reportController->filters($request);
        $eventIds = $this->reportController->scopedEventQuery($filters)->pluck('id');

        $summary = $this->reportController->buildSummary($eventIds, $filters['group']);
        $monthly = $this->reportController->buildMonthly($eventIds, $filters['group']);
        $perEvent = $this->reportController->buildPerEvent($eventIds, $filters['group']);
        $quarterly = $this->reportController->buildQuarterly($eventIds, $filters['group'], $filters);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->buildSummarySheet($spreadsheet, $summary, $filters);
        $this->buildMonthlySheet($spreadsheet, $monthly);
        $this->buildPerEventSheet($spreadsheet, $perEvent);
        $this->buildQuarterlySheet($spreadsheet, $quarterly);
        $this->buildRewardDraftSheet($spreadsheet);

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'omeo_sport_export_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download($tempFile, 'OMEO_Sport_Report.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Angka yang null berarti "difilter keluar oleh group=mko/non_mko"
     * (lihat ReportController::includeMko/includeNonMko) — bukan nol
     * sungguhan. Ditulis sebagai teks eksplisit agar tidak disalahartikan
     * sebagai angka 0 di spreadsheet.
     */
    private function cell($value)
    {
        return $value ?? 'n/a (difilter grup)';
    }

    private function headerStyle($sheet, string $range, string $color = 'FF4F46E5'): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    private function buildSummarySheet(Spreadsheet $spreadsheet, array $summary, array $filters): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Ringkasan');

        $sheet->setCellValue('A1', 'OMEO Sport — Ringkasan Laporan');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->setCellValue('A2', 'Filter: dari ' . ($filters['from'] ?? '-') . ' sampai ' . ($filters['to'] ?? '-') . ', sport: ' . ($filters['sport'] ?? 'semua') . ', grup peserta: ' . $filters['group']);

        $rows = [
            ['Total Event', $summary['total_events']],
            ['Peserta Unik MKO', $summary['unique_participants_mko']],
            ['Peserta Unik Non-MKO', $summary['unique_participants_non_mko']],
            ['Kehadiran (orang-sesi) MKO', $summary['attendance_sessions_mko']],
            ['Kehadiran (orang-sesi) Non-MKO', $summary['attendance_sessions_non_mko']],
            ['Pembatalan Disetujui', $summary['cancellations_approved']],
            ['Belum Terkonfirmasi (event sudah lewat)', $summary['not_confirmed']],
        ];

        $row = 4;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value ?? 'n/a (difilter grup)');
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(14);
    }

    private function buildMonthlySheet(Spreadsheet $spreadsheet, array $monthly): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Bulanan');

        $headers = ['Bulan', 'Jumlah Event', 'Unik MKO', 'Unik Non-MKO', 'Sesi MKO', 'Sesi Non-MKO'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . '1', $h);
        }
        $this->headerStyle($sheet, 'A1:F1');

        $row = 2;
        foreach ($monthly as $m) {
            $sheet->setCellValue("A{$row}", $m['month']);
            $sheet->setCellValue("B{$row}", $m['events']);
            $sheet->setCellValue("C{$row}", $this->cell($m['unique_mko']));
            $sheet->setCellValue("D{$row}", $this->cell($m['unique_non_mko']));
            $sheet->setCellValue("E{$row}", $this->cell($m['sessions_mko']));
            $sheet->setCellValue("F{$row}", $this->cell($m['sessions_non_mko']));
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function buildPerEventSheet(Spreadsheet $spreadsheet, array $perEvent): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Per Event');

        $headers = ['ID', 'Sport', 'Judul', 'Tanggal', 'Coach?', 'Kuota', 'Registrasi', 'Hadir MKO', 'Hadir Non-MKO', 'Status'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . '1', $h);
        }
        $this->headerStyle($sheet, 'A1:J1', 'FF059669');

        $row = 2;
        foreach ($perEvent as $e) {
            $sheet->setCellValue("A{$row}", $e['id']);
            $sheet->setCellValue("B{$row}", $e['sport']);
            $sheet->setCellValue("C{$row}", $e['title']);
            $sheet->setCellValue("D{$row}", $e['event_date']);
            $sheet->setCellValue("E{$row}", $e['has_coach'] ? 'Ya' : 'Tidak');
            $sheet->setCellValue("F{$row}", $e['capacity'] ?? '-');
            $sheet->setCellValue("G{$row}", $this->cell($e['registrations']));
            $sheet->setCellValue("H{$row}", $this->cell($e['attendances_mko']));
            $sheet->setCellValue("I{$row}", $this->cell($e['attendances_non_mko']));
            $sheet->setCellValue("J{$row}", $e['status']);
            $row++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function buildQuarterlySheet(Spreadsheet $spreadsheet, array $quarterly): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Triwulanan');

        $sheet->setCellValue('A1', $quarterly['note']);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setItalic(true)->setSize(9);
        if ($quarterly['type'] === 'unconfigured') {
            $sheet->getStyle('A1')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF92400E'));
        }

        $labelHeader = $quarterly['type'] === 'fiscal' ? 'Fiscal Quarter' : 'Periode (BUKAN quarter fiskal)';
        $headers = [$labelHeader, 'Jumlah Event', 'Unik MKO', 'Unik Non-MKO', 'Sesi MKO', 'Sesi Non-MKO'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . '2', $h);
        }
        $this->headerStyle($sheet, 'A2:F2', 'FF1E40AF');

        $row = 3;
        foreach ($quarterly['rows'] as $q) {
            $sheet->setCellValue("A{$row}", $q['label']);
            $sheet->setCellValue("B{$row}", $q['events']);
            $sheet->setCellValue("C{$row}", $this->cell($q['unique_mko']));
            $sheet->setCellValue("D{$row}", $this->cell($q['unique_non_mko']));
            $sheet->setCellValue("E{$row}", $this->cell($q['sessions_mko']));
            $sheet->setCellValue("F{$row}", $this->cell($q['sessions_non_mko']));
            $row++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function buildRewardDraftSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Reward (Draft)');

        $sheet->setCellValue('A1', 'DRAFT — BELUM DISETUJUI — TIDAK DIBAYARKAN');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF991B1B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEE2E2']],
        ]);
        $sheet->mergeCells('A1:B1');

        $config = RewardConfig::where('sport', 'badminton')->first();
        if (! $config) {
            $sheet->setCellValue('A3', 'Belum ada draft konfigurasi reward badminton tersimpan.');
            return;
        }

        $rows = [
            ['Status', $config->status],
            ['Tier 1 (sesi min-max)', "{$config->tier1_min_sessions}-{$config->tier1_max_sessions}"],
            ['Tier 1 Tarif', $config->tier1_rate],
            ['Tier 2 (sesi min-max)', "{$config->tier2_min_sessions}-{$config->tier2_max_sessions}"],
            ['Tier 2 Tarif', $config->tier2_rate],
            ['Tier 3 (sesi min, pembanding)', "{$config->tier3_min_sessions} ({$config->tier3_comparison})"],
            ['Tier 3 Tarif', $config->tier3_rate],
            ['Periode', $config->period_label],
            ['Tanggal Efektif', $config->effective_date],
        ];

        $row = 3;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value ?? '-');
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(32);
        $sheet->getColumnDimension('B')->setWidth(24);
    }
}
