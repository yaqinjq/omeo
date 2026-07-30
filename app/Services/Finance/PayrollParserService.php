<?php

namespace App\Services\Finance;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PayrollParserService
{
    private const SECTION_HEADERS = [
        'BAR', 'FINANCE AND ACCOUNTING', 'HUMAN RESOURCE DEPARTMENT',
        'KITCHEN OUTLET', 'KITCHEN PRODUCTION', 'MANAGEMENT',
        'PURCHASING', 'SERVICE',
        'DESIGN MARKETING', 'OFFICE PRODUCTION',
    ];

    /**
     * Parse CSV in Ah Pek Kopitiam format.
     * Semicolon-delimited, 40+ columns, BPJS TK=col[34], JKes=col[35] (0-indexed)
     * Returns ['periode' => ['tahun' => int, 'bulan' => int], 'rows' => array]
     */
    public function parseCsv(string $filePath): array
    {
        $rows           = [];
        $currentOutlet  = null;
        $currentSubDept = null;
        $periode        = ['tahun' => null, 'bulan' => null];

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Tidak bisa membuka file: {$filePath}");
        }

        // Skip UTF-8 BOM jika ada
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        $lineNum = 0;
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            $lineNum++;
            $line = array_map('trim', $line);
            $col0 = $line[0] ?? '';

            // Baris kosong — skip
            if ($col0 === '' && empty(array_filter($line))) {
                continue;
            }

            // Ekstrak periode dari baris "Payroll Date : 01 Apr 2026 To 30 Apr 2026"
            if ($lineNum <= 5 && str_contains($col0, 'Payroll Date')) {
                $periode = $this->extractPeriodeFromPayrollDate($col0);
            }

            // Skip baris header berulang
            if ($col0 === 'No.' || $col0 === 'No') {
                continue;
            }

            // Skip metadata / summary lines
            $skipPhrases = [
                'Payroll Session', 'Payroll Date', 'Department :',
                'Tgl. Cetak', 'Hal.', 'Grand Total',
            ];
            foreach ($skipPhrases as $phrase) {
                if (str_starts_with($col0, $phrase) || str_contains($col0, $phrase)) {
                    continue 2;
                }
            }
            if (str_starts_with($col0, 'Total') || str_starts_with($col0, 'Jumlah')) {
                continue;
            }

            // Periksa apakah semua kolom selain col0 kosong
            $restEmpty = true;
            for ($i = 1; $i < count($line); $i++) {
                if ($line[$i] !== '') {
                    $restEmpty = false;
                    break;
                }
            }

            if ($restEmpty && $col0 !== '') {
                $col0Upper = strtoupper($col0);

                // Section header = sub-dept (whitelist)
                if (in_array($col0Upper, self::SECTION_HEADERS)) {
                    $currentSubDept = $col0Upper;
                    continue;
                }

                // Outlet header = baris single-col non-numeric yang bukan metadata
                if (!is_numeric($col0)) {
                    $currentOutlet  = $col0;
                    $currentSubDept = null; // reset sub-dept saat outlet berganti
                    continue;
                }
            }

            // Baris data: col0 berisi angka positif
            if (!is_numeric($col0) || (int) $col0 <= 0) {
                continue;
            }

            $noKomp = $line[1] ?? '';
            $nama   = $line[2] ?? '';
            if ($noKomp === '' && $nama === '') {
                continue;
            }

            // Attd dan HR: "26.00.00" → ambil integer sebelum titik pertama
            $attd = (float) explode('.', $line[6] ?? '0')[0];
            $hr   = (float) explode('.', $line[8] ?? '0')[0];

            // Join date: coba beberapa format
            $joinDate    = null;
            $joinDateStr = $line[5] ?? '';
            if ($joinDateStr !== '') {
                foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $fmt) {
                    $dt = \DateTime::createFromFormat($fmt, $joinDateStr);
                    if ($dt && $dt->format($fmt) === $joinDateStr) {
                        $joinDate = $dt->format('Y-m-d');
                        break;
                    }
                }
                if ($joinDate === null) {
                    $joinDate = $this->parseDate($joinDateStr);
                }
            }

            $rows[] = [
                'no_komp'         => $noKomp,
                'nik'             => null,
                'nama'            => $nama,
                'outlet_name'     => $currentOutlet ?? ($line[3] ?? ''),
                'posisi'          => $line[4] ?? '',
                'sub_dept'        => $currentSubDept,
                'join_date'       => $joinDate,
                'attd'            => $attd,
                'hr'              => $hr,
                'gaji_pokok'      => $this->parseMoney($line[7] ?? ''),
                's_expense'       => $this->parseMoney($line[12] ?? ''),
                'total'           => $this->parseMoney($line[36] ?? ''),
                'ot1_amount'      => $this->parseMoney($line[14] ?? ''),
                'ot2_amount'      => $this->parseMoney($line[16] ?? ''),
                'tunjangan_total' => $this->parseMoney($line[22] ?? '')
                                   + $this->parseMoney($line[23] ?? '')
                                   + $this->parseMoney($line[24] ?? '')
                                   + $this->parseMoney($line[25] ?? '')
                                   + $this->parseMoney($line[26] ?? '')
                                   + $this->parseMoney($line[27] ?? '')
                                   + $this->parseMoney($line[28] ?? '')
                                   + $this->parseMoney($line[29] ?? '')
                                   + $this->parseMoney($line[30] ?? '')
                                   + $this->parseMoney($line[31] ?? '')
                                   + $this->parseMoney($line[32] ?? ''),
                'potongan_total'  => $this->parseMoney($line[19] ?? '')
                                   + $this->parseMoney($line[21] ?? ''),
                'raw_csv_json'    => json_encode($line),
                'bpjs_tk_raw'     => $this->parseMoney($line[34] ?? ''),
                'bpjs_jkes_raw'   => $this->parseMoney($line[35] ?? ''),
                'total_gaji'      => $this->parseMoney($line[36] ?? ''),
                'bank_name'       => $line[37] ?? '',
                'no_rekening'     => $this->cleanRekening($line[38] ?? ''),
                'tanggal_resign'  => $this->parseDate($line[39] ?? ''),
                'source_format'   => 'csv',
            ];
        }

        fclose($handle);

        return [
            'periode' => $periode,
            'rows'    => $rows,
        ];
    }

    /**
     * Ekstrak tahun dan bulan dari "Payroll Date :  01 Apr 2026  To  30 Apr 2026"
     */
    private function extractPeriodeFromPayrollDate(string $str): array
    {
        $bulanMap = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'mei' => 5, 'jun' => 6, 'jul' => 7,
            'aug' => 8, 'agu' => 8, 'sep' => 9, 'oct' => 10,
            'okt' => 10, 'nov' => 11, 'dec' => 12, 'des' => 12,
        ];
        if (preg_match('/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/u', $str, $m)) {
            $bulanStr = strtolower($m[2]);
            return [
                'tahun' => (int) $m[3],
                'bulan' => $bulanMap[$bulanStr] ?? 0,
            ];
        }
        return ['tahun' => null, 'bulan' => null];
    }

    /**
     * Parse XLSX in Tokio-O! format.
     * 43 columns (3 NaN from merged cells), BPJS TK=col38(1-idx), JKes=col39(1-idx)
     */
    public function parseXlsx(string $filePath, string $periode, ?string $sheetName = null): array
    {
        $sheetName   = $sheetName ?? $this->detectSheetName($periode);
        $spreadsheet = IOFactory::load($filePath);

        $sheet = $spreadsheet->getSheetByName($sheetName)
            ?? $spreadsheet->getSheetByName('ALL');

        if (! $sheet) {
            throw new \RuntimeException("Sheet '{$sheetName}' tidak ditemukan di file XLSX.");
        }

        $rows       = [];
        $highestRow = $sheet->getHighestRow();

        for ($i = 1; $i <= $highestRow; $i++) {
            $col0 = trim((string) ($sheet->getCellByColumnAndRow(1, $i)->getValue() ?? ''));

            if (! is_numeric($col0) || (int) $col0 <= 0) {
                continue;
            }

            $noKomp = trim((string) ($sheet->getCellByColumnAndRow(2, $i)->getValue() ?? ''));
            if ($noKomp === '') {
                continue;
            }

            $rows[] = [
                'no_komp'        => $noKomp,
                'nik'            => null,
                'nama'           => trim((string) ($sheet->getCellByColumnAndRow(3, $i)->getValue() ?? '')),
                'outlet_name'    => trim((string) ($sheet->getCellByColumnAndRow(4, $i)->getValue() ?? '')),
                'posisi'         => trim((string) ($sheet->getCellByColumnAndRow(6, $i)->getValue() ?? '')),
                'join_date'      => $this->parseDateFromSpreadsheetValue(
                    $sheet->getCellByColumnAndRow(8, $i)->getValue()
                ),
                'gaji_pokok'     => (float) ($sheet->getCellByColumnAndRow(11, $i)->getValue() ?? 0),
                'bpjs_tk_raw'    => (float) ($sheet->getCellByColumnAndRow(38, $i)->getValue() ?? 0),
                'bpjs_jkes_raw'  => (float) ($sheet->getCellByColumnAndRow(39, $i)->getValue() ?? 0),
                'total_gaji'     => (float) ($sheet->getCellByColumnAndRow(40, $i)->getValue() ?? 0),
                'bank_name'      => trim((string) ($sheet->getCellByColumnAndRow(41, $i)->getValue() ?? '')),
                // Baca sebagai nilai numerik langsung → hindari PHP auto-scientific-notation
                'no_rekening'    => $this->cleanRekeningFromCellValue(
                    $sheet->getCellByColumnAndRow(42, $i)->getValue()
                ),
                'tanggal_resign' => $this->parseDateFromSpreadsheetValue(
                    $sheet->getCellByColumnAndRow(43, $i)->getValue()
                ),
                'source_format'  => 'xlsx',
                'periode'        => $periode,
            ];
        }

        return $rows;
    }

    public function detectBrand(string $filePath, string $fileType): string
    {
        if ($fileType === 'csv') {
            $handle = fopen($filePath, 'r');
            for ($i = 0; $i < 10; $i++) {
                $line = fgetcsv($handle, 0, ';');
                if ($line && str_contains(strtoupper($line[0] ?? ''), 'AH PEK')) {
                    fclose($handle);
                    return 'ah_pek';
                }
                if ($line && str_contains(strtoupper($line[0] ?? ''), 'TOKIO')) {
                    fclose($handle);
                    return 'tokio';
                }
            }
            fclose($handle);
        } elseif (in_array($fileType, ['xlsx', 'xls'])) {
            try {
                $spreadsheet = IOFactory::load($filePath);
                $sheetNames  = $spreadsheet->getSheetNames();
                if (in_array('MEI', $sheetNames) || in_array('JAN', $sheetNames)) {
                    return 'tokio';
                }
            } catch (\Exception) {
                // ignore
            }
        }

        return 'other';
    }

    public function detectPeriode(string $filePath, string $fileType, string $fileName): ?string
    {
        // Try from filename: 2026_04_... or 2026-04-...
        if (preg_match('/(\d{4})[_-](\d{2})[_-]/', $fileName, $m)) {
            return $m[1] . '-' . $m[2];
        }

        if ($fileType === 'csv') {
            $handle = fopen($filePath, 'r');
            for ($i = 0; $i < 3; $i++) {
                $line = fgetcsv($handle, 0, ';');
                $text = $line[0] ?? '';
                // "Payroll Date :  01 Apr 2026  To  30 Apr 2026"
                if (preg_match('/(\d{2})\s+(\w+)\s+(\d{4})/', $text, $m)) {
                    $monthMap = [
                        'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04',
                        'May' => '05', 'Mei' => '05', 'Jun' => '06', 'Jul' => '07',
                        'Aug' => '08', 'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12',
                    ];
                    $monthNum = $monthMap[$m[2]] ?? null;
                    if ($monthNum) {
                        fclose($handle);
                        return $m[3] . '-' . $monthNum;
                    }
                }
            }
            fclose($handle);
        }

        return null;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function detectSheetName(string $periode): string
    {
        $month = (int) substr($periode, 5, 2);

        return match ($month) {
            1  => 'JAN',
            2  => 'FEB',
            3  => 'MAR',
            4  => 'APR',
            5  => 'MEI',
            6  => 'JUN',
            7  => 'JUL',
            8  => 'AUG',
            9  => 'SEPT',
            10 => 'OCT',
            11 => 'NOV',
            12 => 'DES',
            default => 'ALL',
        };
    }

    private function parseMoney(mixed $val): float
    {
        if (is_numeric($val)) {
            return (float) $val;
        }
        $str = trim((string) $val);
        if ($str === '' || preg_match('/^0+\.?0*$/', $str)) {
            return 0.0;
        }
        return (float) str_replace(',', '', $str);
    }

    private function parseDate(string $val): ?string
    {
        if ($val === '') {
            return null;
        }
        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDateFromSpreadsheetValue(mixed $val): ?string
    {
        if (empty($val)) {
            return null;
        }

        if (is_numeric($val)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val)
                    ->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        }

        if ($val instanceof \DateTime) {
            return $val->format('Y-m-d');
        }

        return $this->parseDate((string) $val);
    }

    private function cleanRekening(string $val): string
    {
        $trimmed = trim($val);
        if ($trimmed === '') {
            return '';
        }

        // Handle scientific notation dengan titik ATAU koma sebagai decimal separator.
        // Contoh dari CSV: "1,41002E+12" (locale Indonesia) atau "1.41002E+12" (locale EN).
        if (preg_match('/^[\d,\.]+[Ee][+\-]?\d+$/', $trimmed)) {
            $normalized = str_replace(',', '.', $trimmed);
            return number_format((float) $normalized, 0, '', '');
        }

        return $trimmed;
    }

    /**
     * Baca nomor rekening dari cell PhpSpreadsheet.
     * Jika nilai berupa float (cell numerik), gunakan sprintf agar tidak kehilangan
     * digit trailing akibat auto-scientific-notation PHP.
     */
    private function cleanRekeningFromCellValue(mixed $value): string
    {
        if (is_null($value) || $value === '') {
            return '';
        }

        // Cell numerik: pakai sprintf untuk hindari "(string)$float" = "1.41002E+12"
        if (is_numeric($value) && (float) $value != 0) {
            return sprintf('%.0f', (float) $value);
        }

        return $this->cleanRekening((string) $value);
    }

    /**
     * Parse data gaji tahunan dari file XLS Tokio-O!
     *
     * Menggunakan dua sumber:
     * 1. Sheet ALL   → data gaji aktual per bulan (Col B=NoKomp, I=TanggalBulan, AN=Total)
     * 2. Sheet SUMMARY TOKIO → identitas NIK per karyawan (Col B=NoKomp, D=NIK)
     *
     * CATATAN TEKNIS:
     * Sheet SUMMARY TOKIO berisi formula SUMIFS (bukan nilai langsung).
     * PhpSpreadsheet tidak bisa mengevaluasi cross-sheet SUMIFS secara reliable.
     * Oleh karena itu kita baca langsung dari sheet ALL sebagai sumber data primer.
     */
    public function parseSummaryTokio(string $filePath, int $tahun): array
    {
        set_time_limit(180);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);

        // ── LANGKAH 1: Ambil NIK dari SUMMARY TOKIO (cols A-G, bukan formula) ──
        $nikMap       = []; // [no_komp_upper => nik]
        $sheetSummary = $spreadsheet->getSheetByName('SUMMARY TOKIO')
                     ?? $spreadsheet->getSheetByName('SUMMARY TOKIO (LAPOR)');

        if ($sheetSummary) {
            $summaryHighest = $sheetSummary->getHighestRow();
            for ($i = 2; $i <= $summaryHighest; $i++) {
                $colA = trim((string)($sheetSummary->getCellByColumnAndRow(1, $i)->getValue() ?? ''));
                if (!is_numeric($colA) || (int)$colA <= 0) continue;

                $noKomp = trim((string)($sheetSummary->getCellByColumnAndRow(2, $i)->getValue() ?? ''));
                $nik    = trim((string)($sheetSummary->getCellByColumnAndRow(4, $i)->getValue() ?? ''));
                if ($noKomp) {
                    $nikMap[strtoupper($noKomp)] = $nik ?: null;
                }
            }
        }

        // ── LANGKAH 2: Baca sheet ALL untuk data gaji aktual per bulan ──────────
        $sheetAll = $spreadsheet->getSheetByName('ALL');
        if (!$sheetAll) {
            throw new \RuntimeException(
                'Sheet "ALL" tidak ditemukan di file. ' .
                'Pastikan file adalah file payroll Tokio-O! (Makmur Ramenjaya Mandiri).'
            );
        }

        $salaryMap   = []; // [no_komp_upper => [bulan_int => total_gaji]]
        $identityMap = []; // [no_komp_upper => identity array]
        $allHighest  = $sheetAll->getHighestRow();

        for ($i = 2; $i <= $allHighest; $i++) {
            $colA = $sheetAll->getCellByColumnAndRow(1, $i)->getValue();
            if (empty($colA)) continue;

            $noKomp = trim((string)($sheetAll->getCellByColumnAndRow(2, $i)->getValue() ?? ''));
            if (empty($noKomp)) continue;

            // Tanggal bulan di kolom I (9) — misal: 2026-01-01 = Januari
            $monthDateVal = $sheetAll->getCellByColumnAndRow(9, $i)->getValue();
            $monthNum     = $this->extractMonthNumber($monthDateVal, $tahun);
            if (!$monthNum) continue;

            $totalGaji = (float)($sheetAll->getCellByColumnAndRow(40, $i)->getValue() ?? 0);

            $noKompKey = strtoupper($noKomp);
            if (!isset($salaryMap[$noKompKey])) {
                $salaryMap[$noKompKey] = [];
            }
            $salaryMap[$noKompKey][$monthNum] = $totalGaji > 0 ? $totalGaji : null;

            // Simpan identity dari baris pertama yang ditemukan
            if (!isset($identityMap[$noKompKey])) {
                $identityMap[$noKompKey] = [
                    'no_komp'     => $noKomp,
                    'nama'        => trim((string)($sheetAll->getCellByColumnAndRow(3, $i)->getValue() ?? '')),
                    'outlet_name' => trim((string)($sheetAll->getCellByColumnAndRow(4, $i)->getValue() ?? '')),
                    'posisi'      => trim((string)($sheetAll->getCellByColumnAndRow(6, $i)->getValue() ?? '')),
                    'join_date'   => $this->parseDateFromSpreadsheetValue(
                        $sheetAll->getCellByColumnAndRow(8, $i)->getValue()
                    ),
                ];
            }
        }

        // ── LANGKAH 3: Gabungkan identity + salary + NIK → array final ──────────
        $monthKeys = [
            1  => 'jan', 2  => 'feb', 3  => 'mar', 4  => 'apr',
            5  => 'mei', 6  => 'jun', 7  => 'jul', 8  => 'aug',
            9  => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'des',
        ];

        $rows = [];
        foreach ($identityMap as $noKompKey => $identity) {
            $gaji       = $salaryMap[$noKompKey] ?? [];
            $bulanAktif = count(array_filter($gaji, fn ($v) => $v !== null && $v > 0));

            $gajiPerBulan = [];
            $totalCalc    = 0;
            foreach ($monthKeys as $num => $key) {
                $val                 = $gaji[$num] ?? null;
                $gajiPerBulan[$key]  = $val;
                if ($val > 0) $totalCalc += $val;
            }

            $rows[] = array_merge($identity, [
                'nik'         => $nikMap[$noKompKey] ?? null,
                'tahun'       => $tahun,
                'gaji'        => $gajiPerBulan,
                'thr'         => null,
                'total'       => $totalCalc > 0 ? $totalCalc : null,
                'bulan_aktif' => $bulanAktif,
            ]);
        }

        return $rows;
    }

    /**
     * Ekstrak nomor bulan (1-12) dari nilai tanggal di spreadsheet.
     * Hanya kembalikan bulan jika tahunnya sesuai parameter $tahun.
     */
    private function extractMonthNumber(mixed $val, int $tahun): ?int
    {
        if (empty($val)) return null;

        $dt = null;

        if (is_numeric($val)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
            } catch (\Exception $e) {
                return null;
            }
        } elseif ($val instanceof \DateTime || $val instanceof \DateTimeImmutable) {
            $dt = $val;
        } else {
            try {
                $dt = new \DateTime((string)$val);
            } catch (\Exception $e) {
                return null;
            }
        }

        if (!$dt) return null;
        if ((int)$dt->format('Y') !== $tahun) return null;

        return (int)$dt->format('n');
    }
}
