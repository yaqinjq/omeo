<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollExportController extends Controller
{
    /**
     * Export template MCM 2.0 Bank Mandiri.
     *
     * Sumber data: payroll_import_rows (bank + salary per periode).
     * Setelah HRD reimport dengan parser yang sudah difix, no_rekening
     * akan tersimpan sebagai string penuh (13 digit), bukan scientific notation.
     *
     * Sheet 1: template MCM 2.0 siap upload ke Mandiri
     * Sheet 2: daftar karyawan Mandiri yang rekeningnya masih RUSAK (perlu reimport)
     */
    private const BULAN_ID = [
        '01' => 'Januari',  '02' => 'Februari', '03' => 'Maret',
        '04' => 'April',    '05' => 'Mei',       '06' => 'Juni',
        '07' => 'Juli',     '08' => 'Agustus',   '09' => 'September',
        '10' => 'Oktober',  '11' => 'November',  '12' => 'Desember',
    ];

    private const BULAN_COL = [
        '01' => 'gaji_jan', '02' => 'gaji_feb', '03' => 'gaji_mar',
        '04' => 'gaji_apr', '05' => 'gaji_mei', '06' => 'gaji_jun',
        '07' => 'gaji_jul', '08' => 'gaji_aug', '09' => 'gaji_sep',
        '10' => 'gaji_okt', '11' => 'gaji_nov', '12' => 'gaji_des',
    ];

    public function exportMandiriTemplate(Request $request)
    {
        set_time_limit(120);

        $periode = $request->input('periode', now()->format('Y-m'));

        // Validasi format periode
        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
            return back()->with('error', 'Format periode tidak valid. Gunakan format YYYY-MM.');
        }

        [$year, $month] = explode('-', $periode);

        // Pakai array statis — hindari Carbon locale loading yang lambat
        $periodeLabel = (self::BULAN_ID[$month] ?? $month) . ' ' . $year;

        $keterangan = $request->input('keterangan', 'GAJI MKO GROUP ' . $periodeLabel);

        $format = $request->input('format', 'xlsx'); // default xlsx

        // ── Baris valid (rekening benar, siap transfer) ───────────────────────
        $validRows = DB::table('payroll_import_rows as pir')
            ->join('payroll_import_sessions as pis', 'pir.session_id', '=', 'pis.id')
            ->where('pis.periode', $year . '-' . $month)
            ->whereIn('pis.status', ['completed', 'needs_review'])
            ->whereRaw('UPPER(pir.bank_name) LIKE ?', ['%MANDIRI%'])
            ->where('pir.total_gaji', '>', 0)
            ->where(function ($q) {
                // Rekening tidak broken: flag null (import baru setelah fix) ATAU recovered
                $q->whereNull('pir.rekening_flag')
                  ->orWhere('pir.rekening_flag', 'recovered');
            })
            ->whereRaw('pir.no_rekening NOT LIKE ?', ['RUSAK:%'])
            ->whereRaw('LENGTH(TRIM(pir.no_rekening)) >= 10')
            ->whereNotNull('pir.no_rekening')
            ->select([
                DB::raw('TRIM(pir.no_rekening) as no_rekening'),
                'pir.nama',
                'pir.total_gaji as nominal',
                'pir.bank_name',
                'pir.no_komp',
            ])
            ->orderBy('pir.nama')
            ->get();

        // ── Baris broken (rekening rusak, perlu reimport) ─────────────────────
        $brokenRows = DB::table('payroll_import_rows as pir')
            ->join('payroll_import_sessions as pis', 'pir.session_id', '=', 'pis.id')
            ->where('pis.periode', $year . '-' . $month)
            ->whereIn('pis.status', ['completed', 'needs_review'])
            ->whereRaw('UPPER(pir.bank_name) LIKE ?', ['%MANDIRI%'])
            ->where('pir.rekening_flag', 'broken')
            ->select([
                'pir.no_komp',
                'pir.nama',
                'pir.bank_name',
                DB::raw("REPLACE(pir.no_rekening, 'RUSAK:', '') as no_rekening_asli"),
                'pir.total_gaji as nominal',
            ])
            ->orderBy('pir.nama')
            ->get();

        if ($validRows->isEmpty() && $brokenRows->isEmpty()) {
            return back()->with('error',
                "Tidak ada karyawan Bank Mandiri untuk periode {$periodeLabel}.");
        }

        $filename = "MCM2_MANDIRI_{$year}{$month}.xlsx";

        if ($format === 'csv') {
            return $this->exportMandiriCsv($validRows, $keterangan, $filename);
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("MCM2 Mandiri {$year}{$month}")
            ->setCreator('OMEO HR Suite');

        // ── Sheet 1: Template MCM 2.0 ─────────────────────────────────────────
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('MCM2.0');

        $this->buildMcm2Sheet($sheet1, $validRows, $periodeLabel, $year, $month, $keterangan);

        // ── Sheet 2: Log Rekening Rusak (jika ada) ────────────────────────────
        if ($brokenRows->isNotEmpty()) {
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Rekening Rusak');
            $this->buildBrokenSheet($sheet2, $brokenRows, $periodeLabel);
        }

        $spreadsheet->setActiveSheetIndex(0);

        // Stream ke browser
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false); // skip formula recalculation — percepat write

        $tempPath = tempnam(sys_get_temp_dir(), 'mcm2_');
        $writer->save($tempPath);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ─── Export Total Gaji per Brand (Annual Summary) ────────────────────────

    public function exportTotalGaji(Request $request)
    {
        $periode = $request->input('periode', now()->format('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
            return back()->with('error', 'Format periode tidak valid. Gunakan format YYYY-MM.');
        }

        [$year, $month] = explode('-', $periode);

        $col = self::BULAN_COL[$month] ?? null;
        if (!$col) {
            return back()->with('error', 'Bulan tidak valid.');
        }

        $periodeLabel = (self::BULAN_ID[$month] ?? $month) . ' ' . $year;

        $rows = DB::table('payroll_annual_summaries')
            ->select('outlet_name_raw', DB::raw("SUM({$col}) as total_gaji"))
            ->where('tahun', $year)
            ->whereNull('deleted_at')
            ->where($col, '>', 0)
            ->groupBy('outlet_name_raw')
            ->orderBy('outlet_name_raw')
            ->get();

        if ($rows->isEmpty()) {
            return back()->with('error', "Tidak ada data gaji untuk periode {$periodeLabel}.");
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Total Gaji {$periodeLabel}")
            ->setCreator('OMEO HR Suite');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Total Gaji');

        $sheet->setCellValue('A1', "Rekapitulasi Total Gaji per Brand — {$periodeLabel}");
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $sheet->fromArray(['Nama Brand', 'Bulan', 'Total Gaji'], null, 'A3');
        $sheet->getStyle('A3:C3')->getFont()->setBold(true);
        $sheet->getStyle('A3:C3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EDE9FE');

        foreach ($rows as $i => $row) {
            $r = 4 + $i;
            $sheet->setCellValue("A{$r}", $row->outlet_name_raw);
            $sheet->setCellValue("B{$r}", $periodeLabel);
            $sheet->setCellValue("C{$r}", (float) $row->total_gaji);
        }

        $count = $rows->count();
        if ($count > 0) {
            $lastDataRow = 3 + $count;
            $sheet->getStyle("C4:C{$lastDataRow}")
                  ->getNumberFormat()->setFormatCode('#,##0');
        }

        // Baris total
        $totalRow = 4 + $count;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL');
        $sheet->setCellValue("C{$totalRow}", (float) $rows->sum('total_gaji'));
        $sheet->getStyle("A{$totalRow}:C{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(20);

        $filename = "TotalGaji_{$year}{$month}.xlsx";

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $tempPath = tempnam(sys_get_temp_dir(), 'totalgaji_');
        $writer->save($tempPath);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ─── Export CSV (format=csv) ──────────────────────────────────────────────

    private function exportMandiriCsv(
        \Illuminate\Support\Collection $rows,
        string $keterangan,
        string $baseFilename
    ): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = str_replace('.xlsx', '.csv', $baseFilename);

        return response()->streamDownload(function() use ($rows, $keterangan) {
            $handle = fopen('php://output', 'w');

            // Header MCM 2.0 rows 1-6
            fputcsv($handle, ['Batch Upload MCM 2.0']);
            fputcsv($handle, ['Harus / Mandatory']);
            fputcsv($handle, ['Pilihan / Optional']);
            fputcsv($handle, []);
            fputcsv($handle, ['BANK MANDIRI - MKO GROUP']);
            fputcsv($handle, [
                'P', date('Ymd'), '',
                $rows->count(),
                $rows->sum('nominal'),
                '','','','','','','','','','','','','','','','',
                '','','','','','','','','','','','','','','','',
                '','','','','','','',''
            ]);

            // Data per karyawan
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->no_rekening,
                    $row->nama,
                    '','','',
                    'IDR',
                    $row->nominal,
                    $keterangan,
                    '',
                    'IBU',
                    '',
                    'MANDIRI',
                    'SURABAYA',
                    '','','','','','','','','','','','','','',
                    '','','','','','','','','','','',
                    'N',
                    '','','','','','',
                    'OUR','1','E'
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ─── Sheet 1: Format MCM 2.0 Mandiri ─────────────────────────────────────

    private function buildMcm2Sheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        \Illuminate\Support\Collection $rows,
        string $periodeLabel,
        string $year,
        string $month,
        string $keterangan
    ): void {
        $totalNominal = $rows->sum('nominal');
        $count        = $rows->count();
        $batchDate    = date('Ymd');
        $outletName   = config('app.mandiri_outlet_name', 'MKO GROUP');

        // Baris 1–5: Header MCM 2.0
        $sheet->setCellValue('A1', 'Batch Upload MCM 2.0');
        $sheet->setCellValue('A2', 'Harus / Mandatory');
        $sheet->setCellValue('A3', 'Pilihan / Optional');
        $sheet->setCellValue('A4', '');
        $sheet->setCellValue('A5', "BANK MANDIRI - {$outletName}");

        // Baris 6: Header batch (baris P)
        // Format: P | Tanggal | kosong | JumlahTransaksi | TotalNominal | kosong x39
        $batchRow = array_merge(
            ['P', $batchDate, '', $count, $totalNominal],
            array_fill(0, 39, '')
        );
        $sheet->fromArray($batchRow, null, 'A6');

        // Format nominal baris 6 sebagai angka
        $sheet->getStyle('E6')->getNumberFormat()->setFormatCode('#,##0');

        // Baris 7+: Data per karyawan — tulis data dulu, format belakangan
        $emptyTail   = array_fill(0, 30, ''); // kolom N–AQ
        $dataRowBase = ['', '', '', '', '', 'IDR', 0, $keterangan, '', 'IBU', '', 'MANDIRI', 'SURABAYA'];

        foreach ($rows as $i => $row) {
            $rowNum  = 7 + $i;
            $dataRow = array_merge(
                [
                    $row->no_rekening, // A
                    $row->nama,        // B
                    '', '', '',        // C-E
                    'IDR',             // F
                    (float) $row->nominal, // G
                    $keterangan,       // H
                    '',                // I
                    'IBU',             // J
                    '',                // K
                    'MANDIRI',         // L
                    'SURABAYA',        // M
                ],
                $emptyTail,            // N-AQ (30 kosong)
                ['OUR', '1', 'E']      // AR-AT
            );
            $sheet->fromArray($dataRow, null, "A{$rowNum}");
        }

        // Terapkan format angka ke seluruh kolom G sekaligus (bukan per-baris)
        if ($count > 0) {
            $lastDataRow = 6 + $count;
            $sheet->getStyle("G6:G{$lastDataRow}")
                  ->getNumberFormat()->setFormatCode('#,##0');
        }

        // Lebar kolom penting
        $sheet->getColumnDimension('A')->setWidth(20); // No Rekening
        $sheet->getColumnDimension('B')->setWidth(35); // Nama
        $sheet->getColumnDimension('G')->setWidth(18); // Nominal
        $sheet->getColumnDimension('H')->setWidth(30); // Keterangan
    }

    // ─── Sheet 2: Log Rekening Rusak ─────────────────────────────────────────

    private function buildBrokenSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        \Illuminate\Support\Collection $rows,
        string $periodeLabel
    ): void {
        $sheet->setCellValue('A1', "Karyawan Bank Mandiri — Rekening Belum Valid ({$periodeLabel})");
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('A2', 'Keterangan: Karyawan ini tidak masuk template MCM2.0 karena nomor rekening masih rusak (scientific notation dari import lama). Lakukan reimport file payroll untuk memperbaiki.');
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        // Header tabel
        $headers = ['No Komp', 'Nama', 'Bank', 'No Rekening Asli (Rusak)', 'Nominal Gaji'];
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:E4')->getFont()->setBold(true);
        $sheet->getStyle('A4:E4')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FEE2E2'); // merah muda

        foreach ($rows as $i => $row) {
            $sheet->fromArray([
                $row->no_komp,
                $row->nama,
                $row->bank_name,
                $row->no_rekening_asli,
                (float) $row->nominal,
            ], null, 'A' . (5 + $i));
        }

        if ($rows->isNotEmpty()) {
            $lastRow = 4 + $rows->count();
            $sheet->getStyle("E5:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(18);
    }
}
