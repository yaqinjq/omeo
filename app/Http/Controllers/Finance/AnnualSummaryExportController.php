<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceBpjsRecord;
use App\Models\PayrollAnnualSummary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnnualSummaryExportController extends Controller
{
    private const BULAN_LABELS = [
        1  => 'Januari',  2  => 'Februari', 3  => 'Maret',    4  => 'April',
        5  => 'Mei',      6  => 'Juni',     7  => 'Juli',     8  => 'Agustus',
        9  => 'September',10 => 'Oktober',  11 => 'November', 12 => 'Desember',
    ];

    private const BULAN_COLS = [
        1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
        5 => 'mei', 6 => 'jun', 7 => 'jul', 8 => 'aug',
        9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'des',
    ];

    public function export(Request $request): BinaryFileResponse
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $tahun    = (int) $request->input('tahun', now()->year);
        $outletId = $request->input('outlet_id') ? (int) $request->input('outlet_id') : null;

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $activeBulans = FinanceBpjsRecord::where('periode', 'like', $tahun . '-%')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->selectRaw("CAST(SUBSTRING(periode, 6, 2) AS UNSIGNED) as bulan")
            ->distinct()
            ->orderBy('bulan')
            ->pluck('bulan')
            ->map(fn ($b) => (int) $b)
            ->toArray();

        foreach ($activeBulans as $bulan) {
            $this->buildMonthlySheet($spreadsheet, $tahun, $bulan, $outletId);
        }

        $this->buildSummarySheet($spreadsheet, $tahun, $outletId, $activeBulans);

        $suffix   = $outletId ? "_outlet{$outletId}" : '';
        $filename = "AnnualSummary_{$tahun}{$suffix}.xlsx";

        $writer   = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'omeo_export_') . '.xlsx';
        $writer->save($tempFile);

        return response()->download(
            $tempFile,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function buildMonthlySheet(Spreadsheet $spreadsheet, int $tahun, int $bulan, ?int $outletId): void
    {
        $label   = self::BULAN_LABELS[$bulan] ?? "Bulan{$bulan}";
        $periode = sprintf('%04d-%02d', $tahun, $bulan);

        $records = FinanceBpjsRecord::where('periode', $periode)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderBy('outlet_name')
            ->orderBy('nama')
            ->get();

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(substr($label, 0, 31));

        $headers = [
            'No', 'No. Komp', 'Nama', 'Posisi', 'Sub Dept', 'Kategori',
            'Outlet', 'Join Date', 'Gaji Pokok', 'Attd', 'HR', 'S. Expense', 'Total',
        ];

        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . '1', $h);
        }

        $lastCol = chr(65 + count($headers) - 1);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        foreach ($records as $idx => $r) {
            $gajiPokok = (float) ($r->gaji_pokok ?? 0);
            $attd      = (float) ($r->attd ?? 0);
            $hr        = (float) ($r->hr ?? 0);
            $sExpense  = (float) ($r->s_expense ?? 0);
            $total     = $gajiPokok + $attd + $hr + $sExpense;

            $sheet->setCellValue("A{$row}", $idx + 1);
            $sheet->setCellValue("B{$row}", $r->no_komp);
            $sheet->setCellValue("C{$row}", $r->nama);
            $sheet->setCellValue("D{$row}", $r->posisi);
            $sheet->setCellValue("E{$row}", $r->sub_dept);
            $sheet->setCellValue("F{$row}", $r->category);
            $sheet->setCellValue("G{$row}", $r->outlet_name);
            $sheet->setCellValue("H{$row}", $r->join_date_emp ? Carbon::parse($r->join_date_emp)->format('d/m/Y') : '');
            $sheet->setCellValue("I{$row}", $gajiPokok ?: '');
            $sheet->setCellValue("J{$row}", $attd ?: '');
            $sheet->setCellValue("K{$row}", $hr ?: '');
            $sheet->setCellValue("L{$row}", $sExpense ?: '');
            $sheet->setCellValue("M{$row}", $total ?: '');
            $row++;
        }

        // Totals row
        $sheet->setCellValue("B{$row}", 'TOTAL');
        $sheet->setCellValue("I{$row}", $records->sum(fn ($r) => (float) ($r->gaji_pokok ?? 0)));
        $sheet->setCellValue("J{$row}", $records->sum(fn ($r) => (float) ($r->attd ?? 0)));
        $sheet->setCellValue("K{$row}", $records->sum(fn ($r) => (float) ($r->hr ?? 0)));
        $sheet->setCellValue("L{$row}", $records->sum(fn ($r) => (float) ($r->s_expense ?? 0)));
        $sheet->setCellValue("M{$row}", $records->sum(fn ($r) =>
            (float) ($r->gaji_pokok ?? 0) + (float) ($r->attd ?? 0) +
            (float) ($r->hr ?? 0) + (float) ($r->s_expense ?? 0)
        ));
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
        ]);

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        foreach (['I', 'J', 'K', 'L', 'M'] as $col) {
            $sheet->getStyle("{$col}2:{$col}{$row}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }
    }

    private function buildSummarySheet(
        Spreadsheet $spreadsheet,
        int $tahun,
        ?int $outletId,
        array $activeBulans
    ): void {
        $records = PayrollAnnualSummary::where('tahun', $tahun)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderBy('outlet_name_raw')
            ->orderBy('nama')
            ->get();

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('SUMMARY');

        // Headers: No, No.Komp, Nama, Posisi, Outlet, Jan..Des, THR, Total
        $headers = ['No', 'No. Komp', 'Nama', 'Posisi', 'Outlet'];
        foreach (self::BULAN_LABELS as $label) {
            $headers[] = mb_substr($label, 0, 3);
        }
        $headers[] = 'THR';
        $headers[] = 'Total';

        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->colLetter($i) . '1', $h);
        }

        $lastCol      = $this->colLetter(count($headers) - 1);
        $totalColCount = count($headers);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF059669']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $row = 2;
        foreach ($records as $idx => $r) {
            $sheet->setCellValue("A{$row}", $idx + 1);
            $sheet->setCellValue("B{$row}", $r->no_komp);
            $sheet->setCellValue("C{$row}", $r->nama);
            $sheet->setCellValue("D{$row}", $r->posisi);
            $sheet->setCellValue("E{$row}", $r->outlet_name_raw);

            $colIdx = 5;
            foreach (self::BULAN_COLS as $col) {
                $val = (float) ($r->{"gaji_{$col}"} ?? 0);
                $sheet->setCellValue($this->colLetter($colIdx) . $row, $val ?: '');
                $colIdx++;
            }

            $thr   = (float) ($r->thr ?? 0);
            $total = (float) ($r->total_tahunan ?? 0);
            $sheet->setCellValue($this->colLetter($colIdx) . $row, $thr ?: '');
            $sheet->setCellValue($this->colLetter($colIdx + 1) . $row, $total ?: '');
            $row++;
        }

        // Grand total row
        $sheet->setCellValue("C{$row}", 'GRAND TOTAL');
        $colIdx = 5;
        foreach (self::BULAN_COLS as $col) {
            $sum = $records->sum(fn ($r) => (float) ($r->{"gaji_{$col}"} ?? 0));
            $sheet->setCellValue($this->colLetter($colIdx) . $row, $sum ?: '');
            $colIdx++;
        }
        $sheet->setCellValue($this->colLetter($colIdx) . $row,
            $records->sum(fn ($r) => (float) ($r->thr ?? 0)) ?: '');
        $sheet->setCellValue($this->colLetter($colIdx + 1) . $row,
            $records->sum(fn ($r) => (float) ($r->total_tahunan ?? 0)) ?: '');

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1FAE5']],
        ]);

        for ($i = 0; $i < $totalColCount; $i++) {
            $sheet->getColumnDimension($this->colLetter($i))->setAutoSize(true);
        }

        for ($i = 5; $i < $totalColCount; $i++) {
            $col = $this->colLetter($i);
            $sheet->getStyle("{$col}2:{$col}{$row}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }

        // ── Ringkasan Gaji per Kategori ──────────────────────────────────
        if (empty($activeBulans)) {
            return;
        }

        $grandTotalRow    = $row;
        $catStartRow      = $grandTotalRow + 3;
        $catTotalColCount = count($activeBulans) + 2; // label + N bulan + total

        // Section title
        $catTitleEnd = chr(64 + $catTotalColCount); // max 'N' for 12 months
        $sheet->setCellValue("A{$catStartRow}", 'RINGKASAN GAJI PER KATEGORI');
        $sheet->mergeCells("A{$catStartRow}:{$catTitleEnd}{$catStartRow}");
        $sheet->getStyle("A{$catStartRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1E40AF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E7FF']],
        ]);

        // Category table header
        $catHeaderRow = $catStartRow + 1;
        $catHeaders   = ['KATEGORI'];
        foreach ($activeBulans as $m) {
            $catHeaders[] = mb_substr(self::BULAN_LABELS[$m] ?? "Bln{$m}", 0, 3);
        }
        $catHeaders[] = 'TOTAL';

        foreach ($catHeaders as $i => $h) {
            $sheet->setCellValue($this->colLetter($i) . $catHeaderRow, $h);
        }

        $catHeaderEnd = chr(64 + count($catHeaders));
        $sheet->getStyle("A{$catHeaderRow}:{$catHeaderEnd}{$catHeaderRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Category definitions
        // Monthly sheet layout: A=No, B=No.Komp, C=Nama, D=Posisi, E=SubDept,
        //   F=Kategori, G=Outlet, H=JoinDate, I=GajiPokok, J=Attd, K=HR, L=SExpense, M=Total
        $catDefinitions = [
            'bar'     => ['label' => 'BAR',     'color' => 'FFFED7AA'],
            'kitchen' => ['label' => 'KITCHEN', 'color' => 'FFFEF08A'],
            'service' => ['label' => 'SERVICE', 'color' => 'FFBAE6FD'],
            'office'  => ['label' => 'OFFICE',  'color' => 'FFC7D2FE'],
            'prive'   => ['label' => 'PRIVE',   'color' => 'FFE9D5FF'],
            'lainnya' => ['label' => 'LAINNYA', 'color' => 'FFF3F4F6'],
        ];

        $catDataRow = $catHeaderRow + 1;

        foreach ($catDefinitions as $catKey => $catMeta) {
            $catColIdx = 1;

            // Kolom A: label kategori
            $sheet->setCellValue('A' . $catDataRow, $catMeta['label']);
            $sheet->getStyle(chr(64 + $catColIdx) . $catDataRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $catMeta['color']]],
            ]);
            $catColIdx++;

            $firstBulanCol = chr(64 + $catColIdx);

            foreach ($activeBulans as $m) {
                // Sheet name = full month label as set in buildMonthlySheet()
                $sheetName = self::BULAN_LABELS[$m] ?? "Bulan{$m}";
                // F = Kategori, M = Total (confirmed from buildMonthlySheet header array)
                $formula = "=IFERROR(SUMIF('{$sheetName}'!\$F:\$F,\"{$catKey}\",'{$sheetName}'!\$M:\$M),0)";
                $cellRef = chr(64 + $catColIdx) . $catDataRow;
                $sheet->setCellValue($cellRef, $formula);
                $sheet->getStyle($cellRef)->getNumberFormat()->setFormatCode('#,##0');
                $catColIdx++;
            }

            // TOTAL kolom: SUM semua kolom bulan di baris ini
            $lastBulanCol = chr(64 + $catColIdx - 1);
            $totalCellRef = chr(64 + $catColIdx) . $catDataRow;
            $sheet->setCellValue($totalCellRef, "=SUM({$firstBulanCol}{$catDataRow}:{$lastBulanCol}{$catDataRow})");
            $sheet->getStyle($totalCellRef)->applyFromArray([
                'font'         => ['bold' => true],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            $catDataRow++;
        }

        // Baris TOTAL semua kategori
        $catFirstDataRow = $catHeaderRow + 1;
        $catLastDataRow  = $catDataRow - 1;

        $sheet->setCellValue('A' . $catDataRow, 'TOTAL');
        $sheet->getStyle("A{$catDataRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF3CD']],
        ]);

        $colIdx2 = 2;
        foreach ($activeBulans as $m) {
            $colLetter = chr(64 + $colIdx2);
            $sheet->setCellValue(
                $colLetter . $catDataRow,
                "=SUM({$colLetter}{$catFirstDataRow}:{$colLetter}{$catLastDataRow})"
            );
            $sheet->getStyle($colLetter . $catDataRow)->applyFromArray([
                'font'         => ['bold' => true],
                'fill'         => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF3CD']],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);
            $colIdx2++;
        }

        $totalColLetter = chr(64 + $colIdx2);
        $sheet->setCellValue(
            $totalColLetter . $catDataRow,
            "=SUM({$totalColLetter}{$catFirstDataRow}:{$totalColLetter}{$catLastDataRow})"
        );
        $sheet->getStyle($totalColLetter . $catDataRow)->applyFromArray([
            'font'         => ['bold' => true],
            'fill'         => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF3CD']],
            'numberFormat' => ['formatCode' => '#,##0'],
        ]);

        // ── Ringkasan Gaji per Outlet (blok per outlet ke bawah) ────────────
        $categories = ['bar', 'kitchen', 'service', 'office', 'prive', 'lainnya'];
        $catLabels  = ['BAR', 'KITCHEN', 'SERVICE', 'OFFICE', 'PRIVE', 'LAINNYA'];
        $catColors  = [
            'bar'     => 'FFFED7AA',
            'kitchen' => 'FFFEF08A',
            'service' => 'FFBAE6FD',
            'office'  => 'FFC7D2FE',
            'prive'   => 'FFE9D5FF',
            'lainnya' => 'FFF3F4F6',
        ];

        $numBulan  = count($activeBulans);
        $totalCols = 2 + $numBulan + 1; // NO + KATEGORI + bulan... + TOTAL
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        $periodes = array_map(
            fn ($m) => sprintf('%04d-%02d', $tahun, $m),
            $activeBulans
        );

        $rawOutletData = DB::table('finance_bpjs_records')
            ->whereIn('periode', $periodes)
            ->whereNotNull('outlet_name')->where('outlet_name', '!=', '')
            ->whereNotNull('category')->where('category', '!=', '')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->select('outlet_name', 'category', 'periode', DB::raw('SUM(total) as sum_total'))
            ->groupBy('outlet_name', 'category', 'periode')
            ->orderBy('outlet_name')
            ->get();

        $outletPivot = [];
        $allOutlets  = [];
        foreach ($rawOutletData as $row) {
            $m = (int) explode('-', $row->periode)[1];
            $outletPivot[$row->outlet_name][$m][$row->category] =
                ($outletPivot[$row->outlet_name][$m][$row->category] ?? 0) + $row->sum_total;
            $allOutlets[$row->outlet_name] = true;
        }
        ksort($allOutlets);

        // Section title
        $sectionTitleRow = $catDataRow + 3;
        $sheet->setCellValue('A' . $sectionTitleRow, 'RINGKASAN GAJI PER OUTLET PER KATEGORI PER BULAN');
        $sheet->mergeCells('A' . $sectionTitleRow . ':' . $lastColLetter . $sectionTitleRow);
        $sheet->getStyle('A' . $sectionTitleRow)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF1E40AF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E7FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $currentRow = $sectionTitleRow + 2;

        foreach (array_keys($allOutlets) as $outletName) {
            // Baris 1: header nama outlet
            $sheet->setCellValue('A' . $currentRow, $outletName);
            $sheet->mergeCells('A' . $currentRow . ':' . $lastColLetter . $currentRow);
            $sheet->getStyle('A' . $currentRow)->applyFromArray([
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                                'vertical'   => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;

            // Baris 2: header kolom
            $sheet->setCellValue('A' . $currentRow, 'NO.');
            $sheet->setCellValue('B' . $currentRow, 'KATEGORI');
            $colIdx = 3;
            foreach ($activeBulans as $m) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($colIdx) . $currentRow,
                    strtoupper(mb_substr(self::BULAN_LABELS[$m] ?? "Bln{$m}", 0, 3))
                );
                $colIdx++;
            }
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx) . $currentRow, 'TOTAL');

            $sheet->getStyle('A' . $currentRow . ':' . $lastColLetter . $currentRow)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF374151']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $currentRow++;

            // Baris 3-8: data per kategori
            $colTotals  = array_fill(0, $numBulan, 0.0);
            $grandTotal = 0.0;

            foreach ($categories as $catIdx => $cat) {
                $sheet->setCellValue('A' . $currentRow, $catIdx + 1);
                $sheet->setCellValue('B' . $currentRow, $catLabels[$catIdx]);
                $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $catColors[$cat]]],
                ]);
                $sheet->getStyle('B' . $currentRow)->getFont()->setBold(true);

                $colIdx   = 3;
                $rowTotal = 0.0;
                foreach ($activeBulans as $bulanIdx => $m) {
                    $val = $outletPivot[$outletName][$m][$cat] ?? 0;
                    if ($val > 0) {
                        $cl = Coordinate::stringFromColumnIndex($colIdx);
                        $sheet->setCellValue($cl . $currentRow, $val);
                        $sheet->getStyle($cl . $currentRow)->getNumberFormat()->setFormatCode('#,##0');
                    }
                    $rowTotal             += $val;
                    $colTotals[$bulanIdx] += $val;
                    $colIdx++;
                }

                $totalCl = Coordinate::stringFromColumnIndex($colIdx);
                if ($rowTotal > 0) {
                    $sheet->setCellValue($totalCl . $currentRow, $rowTotal);
                    $sheet->getStyle($totalCl . $currentRow)->applyFromArray([
                        'font'         => ['bold' => true],
                        'numberFormat' => ['formatCode' => '#,##0'],
                    ]);
                }
                $grandTotal += $rowTotal;
                $currentRow++;
            }

            // Baris TOTAL per outlet
            $sheet->setCellValue('B' . $currentRow, 'TOTAL');
            $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF3CD']],
            ]);

            $colIdx = 3;
            foreach ($activeBulans as $bulanIdx => $m) {
                $val = $colTotals[$bulanIdx];
                if ($val > 0) {
                    $cl = Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->setCellValue($cl . $currentRow, $val);
                    $sheet->getStyle($cl . $currentRow)->applyFromArray([
                        'font'         => ['bold' => true],
                        'fill'         => ['fillType' => Fill::FILL_SOLID,
                                          'startColor' => ['argb' => 'FFFFF3CD']],
                        'numberFormat' => ['formatCode' => '#,##0'],
                    ]);
                }
                $colIdx++;
            }

            $totalCl = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue($totalCl . $currentRow, $grandTotal);
            $sheet->getStyle($totalCl . $currentRow)->applyFromArray([
                'font'         => ['bold' => true, 'size' => 11],
                'fill'         => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFBBF24']],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            $currentRow += 3; // 2 baris kosong sebelum outlet berikutnya
        }

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(18);
        $colIdx = 3;
        foreach ($activeBulans as $m) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setWidth(18);
            $colIdx++;
        }
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setWidth(18);
    }

    private function colLetter(int $index): string
    {
        if ($index < 26) {
            return chr(65 + $index);
        }
        return chr(64 + intdiv($index, 26)) . chr(65 + ($index % 26));
    }
}
