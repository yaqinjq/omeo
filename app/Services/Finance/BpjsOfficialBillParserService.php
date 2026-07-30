<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BpjsOfficialBillParserService
{
    /**
     * Parse uploaded bill file. Returns:
     * [
     *   'meta' => ['npp', 'nama_perusahaan', 'periode'],
     *   'rows' => [['nik', 'no_peserta', 'nama', 'iuran_jkk', ...], ...]
     * ]
     */
    public function parse(string $filePath, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            return $this->parsePdf($filePath);
        }

        if (in_array($ext, ['csv'])) {
            return $this->parseCsv($filePath);
        }

        return $this->parseExcel($filePath);
    }

    // ── EXCEL ────────────────────────────────────────────────────────────────

    public function parseExcel(string $filePath): array
    {
        set_time_limit(120);

        try {
            // readDataOnly skips styles/drawings/defined-names/merged-cell
            // recalculation — the parts of PhpSpreadsheet most likely to choke
            // on non-standard files (e.g. PDF-to-Excel converters like Adobe
            // often emit unusual sheet dimensions/merged cells). We only need
            // cell values, so this both avoids a common crash and is faster.
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $meta = $this->extractMetaFromExcel($sheet);
            $rows = $this->extractRowsFromExcel($sheet);
        } catch (\PhpOffice\PhpSpreadsheet\Exception|\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            Log::error('BPJS Excel Parse Error: ' . $e->getMessage(), [
                'file'  => basename($filePath),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException(
                'File Excel ini tidak bisa dibaca (struktur file tidak standar — sering terjadi kalau file hasil convert PDF-ke-Excel dari aplikasi pihak ketiga). '
                . 'Coba upload PDF aslinya langsung, atau buka file ini di Excel/Google Sheets lalu simpan ulang sebagai .xlsx baru sebelum upload.'
            );
        }

        return ['meta' => $meta, 'rows' => $rows, 'format' => 'excel'];
    }

    private function extractMetaFromExcel(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $npp = null;
        $namaPerusahaan = null;
        $periode = null;
        $cv = fn(int $col, int $row) => trim((string) ($sheet->getCell([$col, $row])->getValue() ?? ''));

        for ($r = 1; $r <= 20; $r++) {
            for ($c = 1; $c <= 10; $c++) {
                $val = $cv($c, $r);
                if (empty($val)) continue;
                $valLower = strtolower($val);

                if (str_contains($valLower, 'npp') || str_contains($valLower, 'nomor pendaftaran')) {
                    $next = $cv($c + 1, $r) ?: $cv($c + 2, $r);
                    if (!empty($next) && is_numeric(str_replace([' ', '-'], '', $next))) {
                        $npp = preg_replace('/\D/', '', $next);
                    }
                }

                if (str_contains($valLower, 'nama') && str_contains($valLower, 'perusahaan')) {
                    $next = $cv($c + 1, $r) ?: $cv($c + 2, $r);
                    if (!empty($next)) $namaPerusahaan = $next;
                }

                if (str_contains($valLower, 'periode') || str_contains($valLower, 'bulan')) {
                    $next = $cv($c + 1, $r) ?: $cv($c + 2, $r);
                    $parsed = $this->parsePeriode($next);
                    if ($parsed) $periode = $parsed;
                }
            }
        }

        return compact('npp', 'namaPerusahaan', 'periode');
    }

    private function extractRowsFromExcel(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $maxRow = $sheet->getHighestRow();
        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $cv     = fn(int $col, int $row) => $sheet->getCell([$col, $row])->getValue();
        $headerRow = null;
        $colMap    = [];

        for ($r = 1; $r <= min(30, $maxRow); $r++) {
            $found   = 0;
            $tempMap = [];
            for ($c = 1; $c <= $maxCol; $c++) {
                $cell = strtolower(trim((string) ($cv($c, $r) ?? '')));
                if (str_contains($cell, 'nik'))              { $tempMap['nik'] = $c; $found++; }
                if (str_contains($cell, 'nama'))             { $tempMap['nama'] = $c; $found++; }
                if (str_contains($cell, 'peserta') || str_contains($cell, 'kepesertaan')) { $tempMap['no_peserta'] = $c; }
                if (str_contains($cell, 'jkk'))              { $tempMap['iuran_jkk'] = $c; }
                if (str_contains($cell, 'jkm') || str_contains($cell, 'jk ') || $cell === 'jkm') { $tempMap['iuran_jkm'] = $c; }
                if (str_contains($cell, 'jht') && (str_contains($cell, 'tenaga') || str_contains($cell, 'karyawan') || str_contains($cell, 'tk'))) { $tempMap['iuran_jht_employee'] = $c; }
                if (str_contains($cell, 'jht') && (str_contains($cell, 'perusahaan') || str_contains($cell, 'pj'))) { $tempMap['iuran_jht_employer'] = $c; }
                if (str_contains($cell, 'jp')  && (str_contains($cell, 'tenaga') || str_contains($cell, 'karyawan') || str_contains($cell, 'tk'))) { $tempMap['iuran_jp_employee'] = $c; }
                if (str_contains($cell, 'jp')  && (str_contains($cell, 'perusahaan') || str_contains($cell, 'pj'))) { $tempMap['iuran_jp_employer'] = $c; }
                if (str_contains($cell, 'total') || str_contains($cell, 'jumlah iuran')) { $tempMap['total_iuran'] = $c; }
            }
            if ($found >= 2) { $headerRow = $r; $colMap = $tempMap; break; }
        }

        if (!$headerRow) {
            return $this->extractRowsFallback($sheet, $maxRow);
        }

        $rows = [];
        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $nama = trim((string) ($cv($colMap['nama'] ?? 2, $r) ?? ''));
            if (empty($nama) || strtolower($nama) === 'nama') continue;
            if (str_contains(strtolower($nama), 'total') || str_contains(strtolower($nama), 'jumlah')) continue;

            $row = [
                'nik'                => $this->cleanNik($cv($colMap['nik'] ?? 1, $r)),
                'no_peserta'         => trim((string) ($cv($colMap['no_peserta'] ?? 0, $r) ?? '')),
                'nama'               => $nama,
                'iuran_jkk'          => $this->toFloat($cv($colMap['iuran_jkk'] ?? 0, $r)),
                'iuran_jkm'          => $this->toFloat($cv($colMap['iuran_jkm'] ?? 0, $r)),
                'iuran_jht_employee' => $this->toFloat($cv($colMap['iuran_jht_employee'] ?? 0, $r)),
                'iuran_jht_employer' => $this->toFloat($cv($colMap['iuran_jht_employer'] ?? 0, $r)),
                'iuran_jp_employee'  => $this->toFloat($cv($colMap['iuran_jp_employee'] ?? 0, $r)),
                'iuran_jp_employer'  => $this->toFloat($cv($colMap['iuran_jp_employer'] ?? 0, $r)),
                'total_iuran'        => $this->toFloat($cv($colMap['total_iuran'] ?? 0, $r)),
            ];

            if ($row['total_iuran'] == 0) {
                $row['total_iuran'] = $row['iuran_jkk'] + $row['iuran_jkm']
                    + $row['iuran_jht_employee'] + $row['iuran_jht_employer']
                    + $row['iuran_jp_employee']  + $row['iuran_jp_employer'];
            }

            if ($row['total_iuran'] > 0 || !empty($row['nik'])) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    // Fallback: assume fixed column order (no, nik, nama, peserta, jkk, jkm, jht_emp, jht_er, jp_emp, jp_er, total)
    private function extractRowsFallback(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $maxRow): array
    {
        // Kopijaya/format lebar: 28 kolom (A-AB). NIK di kolom D (1-indexed: 4).
        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $sheet->getHighestColumn()
        );
        if ($maxCol >= 26) {
            return $this->extractRowsKopijaya($sheet, $maxRow);
        }

        $rows        = [];
        $dataStarted = false;
        $cv          = fn(int $col, int $row) => $sheet->getCell([$col, $row])->getValue();

        for ($r = 1; $r <= $maxRow; $r++) {
            $col1 = trim((string) ($cv(1, $r) ?? ''));

            if (!$dataStarted && is_numeric($col1) && (int) $col1 >= 1) {
                $dataStarted = true;
            }
            if (!$dataStarted) continue;
            if (!is_numeric($col1)) break;

            $row = [
                'nik'                => $this->cleanNik($cv(2, $r)),
                'no_peserta'         => trim((string) ($cv(4, $r) ?? '')),
                'nama'               => trim((string) ($cv(3, $r) ?? '')),
                'iuran_jkk'          => $this->toFloat($cv(5, $r)),
                'iuran_jkm'          => $this->toFloat($cv(6, $r)),
                'iuran_jht_employee' => $this->toFloat($cv(7, $r)),
                'iuran_jht_employer' => $this->toFloat($cv(8, $r)),
                'iuran_jp_employee'  => $this->toFloat($cv(9, $r)),
                'iuran_jp_employer'  => $this->toFloat($cv(10, $r)),
                'total_iuran'        => $this->toFloat($cv(11, $r)),
            ];

            if (!empty($row['nama'])) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    // Format Kopijaya: 28 kolom (A-AB), 2 baris header (merged), data mulai baris 3.
    // getCellByColumnAndRow lebih stabil dari getCell([$col,$row]) saat ada merged cell.
    private function extractRowsKopijaya(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $maxRow): array
    {
        $rows = [];
        $gcr  = fn(int $col, int $row) => $sheet->getCellByColumnAndRow($col, $row)->getValue();

        for ($r = 3; $r <= $maxRow; $r++) {
            $nik = trim((string) ($gcr(4, $r) ?? ''));  // Col D

            // Skip baris non-data: NIK harus 16 digit
            if (!preg_match('/^\d{16}$/', $nik)) continue;

            $nama = strtoupper(trim((string) ($gcr(6, $r) ?? '')));  // Col F

            // Skip grand total / summary row
            if (empty($nama) || str_contains($nama, 'JUMLAH') || str_contains($nama, 'TOTAL')) continue;

            $rows[] = [
                'nik'                => $nik,
                'no_peserta'         => '',
                'nama'               => $nama,
                'iuran_jkk'          => $this->toFloat($gcr(14, $r)),  // Col N
                'iuran_jkm'          => $this->toFloat($gcr(15, $r)),  // Col O
                'iuran_jht_employer' => $this->toFloat($gcr(16, $r)),  // Col P — JHT Pemberi Kerja
                'iuran_jht_employee' => $this->toFloat($gcr(18, $r)),  // Col R — JHT Tenaga Kerja
                'iuran_jp_employer'  => $this->toFloat($gcr(20, $r)),  // Col T — JP Pemberi Kerja
                'iuran_jp_employee'  => $this->toFloat($gcr(23, $r)),  // Col W — JP Tenaga Kerja
                'total_iuran'        => $this->toFloat($gcr(27, $r)),  // Col AA
            ];
        }

        return $rows;
    }

    // ── CSV ──────────────────────────────────────────────────────────────────

    public function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) throw new \RuntimeException('Tidak dapat membuka file CSV.');

        $rows = [];
        $headerRow = null;
        $colMap = [];
        $lineNum = 0;
        $meta = ['npp' => null, 'namaPerusahaan' => null, 'periode' => null];

        while (($line = fgetcsv($handle, 2000, ',')) !== false) {
            $lineNum++;

            if ($lineNum <= 10 && $headerRow === null) {
                // Try to find meta in early rows
                $flat = implode(' ', $line);
                if (!$meta['npp'] && preg_match('/NPP[:\s]+(\d+)/i', $flat, $m)) $meta['npp'] = $m[1];
                if (!$meta['periode']) { $p = $this->parsePeriode($flat); if ($p) $meta['periode'] = $p; }
            }

            // Detect header
            if ($headerRow === null) {
                $lower = array_map('strtolower', $line);
                $nikIdx = array_search('nik', $lower);
                $namaIdx = array_search('nama', $lower);
                if ($nikIdx !== false || $namaIdx !== false) {
                    $headerRow = $lineNum;
                    foreach ($lower as $i => $h) {
                        if (str_contains($h, 'nik'))      $colMap['nik'] = $i;
                        if (str_contains($h, 'nama'))     $colMap['nama'] = $i;
                        if (str_contains($h, 'peserta'))  $colMap['no_peserta'] = $i;
                        if (str_contains($h, 'jkk'))      $colMap['iuran_jkk'] = $i;
                        if ($h === 'jkm' || str_contains($h, 'jkm')) $colMap['iuran_jkm'] = $i;
                        if (str_contains($h, 'jht') && str_contains($h, 'tk')) $colMap['iuran_jht_employee'] = $i;
                        if (str_contains($h, 'jht') && str_contains($h, 'pj')) $colMap['iuran_jht_employer'] = $i;
                        if (str_contains($h, 'jp')  && str_contains($h, 'tk')) $colMap['iuran_jp_employee'] = $i;
                        if (str_contains($h, 'jp')  && str_contains($h, 'pj')) $colMap['iuran_jp_employer'] = $i;
                        if (str_contains($h, 'total'))    $colMap['total_iuran'] = $i;
                    }
                }
                continue;
            }

            $nama = isset($colMap['nama']) ? trim($line[$colMap['nama']] ?? '') : '';
            if (empty($nama)) continue;

            $row = [
                'nik'                => $this->cleanNik($line[$colMap['nik'] ?? -1] ?? null),
                'no_peserta'         => trim($line[$colMap['no_peserta'] ?? -1] ?? ''),
                'nama'               => $nama,
                'iuran_jkk'          => $this->toFloat($line[$colMap['iuran_jkk'] ?? -1] ?? 0),
                'iuran_jkm'          => $this->toFloat($line[$colMap['iuran_jkm'] ?? -1] ?? 0),
                'iuran_jht_employee' => $this->toFloat($line[$colMap['iuran_jht_employee'] ?? -1] ?? 0),
                'iuran_jht_employer' => $this->toFloat($line[$colMap['iuran_jht_employer'] ?? -1] ?? 0),
                'iuran_jp_employee'  => $this->toFloat($line[$colMap['iuran_jp_employee'] ?? -1] ?? 0),
                'iuran_jp_employer'  => $this->toFloat($line[$colMap['iuran_jp_employer'] ?? -1] ?? 0),
                'total_iuran'        => $this->toFloat($line[$colMap['total_iuran'] ?? -1] ?? 0),
            ];

            if ($row['total_iuran'] == 0) {
                $row['total_iuran'] = $row['iuran_jkk'] + $row['iuran_jkm']
                    + $row['iuran_jht_employee'] + $row['iuran_jht_employer']
                    + $row['iuran_jp_employee']  + $row['iuran_jp_employer'];
            }

            $rows[] = $row;
        }

        fclose($handle);
        return ['meta' => $meta, 'rows' => $rows, 'format' => 'csv'];
    }

    // ── PDF ──────────────────────────────────────────────────────────────────

    public function parsePdf(string $filePath): array
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($filePath);
            $pages  = $pdf->getPages();

            if (empty($pages)) {
                throw new \RuntimeException('PDF tidak memiliki halaman yang bisa dibaca.');
            }

            // Tiap halaman punya blok NIK/nama/angka sendiri-sendiri (bukan satu
            // blok yang menyambung lintas halaman) — jadi diparsing per halaman,
            // BUKAN digabung dulu baru diparsing sekali (itu malah merusak
            // deteksi batas blok NIK karena ada 2+ kelompok NIK terpisah di teks
            // gabungan). Hasil tiap halaman baru digabung setelah diparsing.
            $allRows = [];
            $meta    = ['npp' => null, 'namaPerusahaan' => null, 'periode' => null];
            $hasText = false;

            foreach ($pages as $page) {
                $pageText = $page->getText();
                if (empty(trim($pageText))) continue;
                $hasText = true;

                if ($meta['npp'] === null && $meta['namaPerusahaan'] === null) {
                    $pageMeta = $this->extractMetaFromText($pageText);
                    foreach (['npp', 'namaPerusahaan', 'periode'] as $k) {
                        if (!empty($pageMeta[$k])) $meta[$k] = $pageMeta[$k];
                    }
                }

                $pageRows = $this->extractRowsFromPdfText($pageText);
                $allRows  = array_merge($allRows, $pageRows);
            }

            if (!$hasText) {
                throw new \RuntimeException('PDF tidak mengandung teks yang bisa diekstrak (kemungkinan PDF scan/gambar, bukan digital).');
            }

            return ['meta' => $meta, 'rows' => $allRows, 'format' => 'pdf_digital'];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('BPJS PDF Parse Error: ' . $e->getMessage(), [
                'file'  => basename($filePath),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Gagal parsing PDF: ' . $e->getMessage());
        }
    }

    private function extractMetaFromText(string $text): array
    {
        $npp = null;
        $namaPerusahaan = null;
        $periode = null;

        // NPP: look for "NPP" keyword followed by digits on same or next line
        if (preg_match('/NPP\s*[:\-]?\s*\n?\s*(\d[\d\s\-]*)/i', $text, $m)) {
            $npp = preg_replace('/\D/', '', $m[1]);
            if (strlen($npp) < 4) $npp = null;
        }

        // Nama Perusahaan: take value after keyword, strip tab-duplicates
        if (preg_match('/Nama\s+Perusahaan\s*[:\-]?\s*\n?\s*(.+)/i', $text, $m)) {
            $namaPerusahaan = trim(explode("\t", $m[1])[0]);
        }

        // Periode: try "MM YYYY" (e.g. "05 2026"), "Periode Laporan\nMM YYYY"
        if (preg_match('/Periode[^\n]*\n\s*(\d{2})\s+(\d{4})/i', $text, $m)) {
            $periode = "{$m[2]}-{$m[1]}";
        }
        if (!$periode && preg_match('/Periode\s*[:\-]?\s*(.{3,25})/i', $text, $m)) {
            $periode = $this->parsePeriode(trim($m[1]));
        }
        if (!$periode && preg_match('/(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})/i', $text, $m)) {
            $periode = $this->parsePeriode($m[0]);
        }

        return compact('npp', 'namaPerusahaan', 'periode');
    }

    private function extractRowsFromPdfText(string $text): array
    {
        // Formulir 2a PU PDFs are extracted column-by-column (not row-by-row).
        // All NIKs appear first, then all names, then number columns in sequence.
        // Strategy: identify each data block by position relative to section headers.

        // ── Pre-processing: buang grand total row + tabel ringkasan ──────────
        // "Jumlah Seluruhnya" menandai akhir data karyawan.
        // Teks setelahnya (grand total values, GM/PKM/JBR summary table) akan
        // merusak kolom reconstruction jika ikut ter-parse.
        $cutMarkers = [
            'Jumlah Seluruhnya',
            'Jumlah seluruhnya',
            'JUMLAH SELURUHNYA',
            '*) Isian formulir',
        ];
        foreach ($cutMarkers as $marker) {
            $pos = strpos($text, $marker);
            if ($pos !== false) {
                $text = substr($text, 0, $pos);
                Log::info('BPJS Parser: teks dipotong di marker "' . $marker . '"', [
                    'remaining_length' => strlen($text),
                ]);
                break;
            }
        }

        $lines = preg_split('/\r?\n/', $text);
        $lines = array_values(array_filter(array_map('trim', $lines), fn($l) => $l !== ''));
        $n = count($lines);

        // ── Step 1: Extract NIK block (16-digit lines) ──────────────────────
        $niks = [];
        $nikBlockStart = null;
        $nikBlockEnd   = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\d{16}$/', $line)) {
                if ($nikBlockStart === null) $nikBlockStart = $i;
                $niks[] = $line;
                $nikBlockEnd = $i;
            }
        }
        $count = count($niks);
        if ($count === 0) return [];

        // ── Step 2: Extract no_peserta (alphanumeric codes before NIK block) ─
        $nosPeserta = [];
        for ($i = 0; $i < ($nikBlockStart ?? 0); $i++) {
            $l = $lines[$i];
            // BPJS membership numbers: 9-15 alphanumeric chars containing digits
            // (NPP is typically 8 digits and should be excluded)
            if (preg_match('/^[A-Z0-9]{9,15}$/', $l) && preg_match('/\d/', $l)) {
                $nosPeserta[] = $l;
            }
        }

        // ── Step 3: Extract names (after NIK block, before "Nama Tenaga Kerja") ─
        $namaTKIdx = null;
        for ($i = 0; $i < $n; $i++) {
            if (stripos($lines[$i], 'nama tenaga') !== false) { $namaTKIdx = $i; break; }
        }

        // Names start right after NIK block ends; skip any header-like lines
        $namesStart = ($nikBlockEnd ?? 0) + 1;
        while ($namesStart < $n && !preg_match('/^[A-Z]{2}/u', $lines[$namesStart])) {
            $namesStart++;
        }

        $names = [];
        $namesEnd = $namaTKIdx ?? ($namesStart + $count + 5);
        for ($i = $namesStart; $i < $namesEnd && count($names) < $count; $i++) {
            $l = trim($lines[$i]);
            // Names: uppercase letters, spaces, dots, hyphens — min 2 chars
            if (strlen($l) >= 2 && preg_match('/^[A-Z][A-Z0-9\s\.\'\-]+$/u', $l)) {
                $names[] = $l;
            }
        }

        $names = $this->mergeFragmentedNames($names, count($niks));

        // ── Step 4: Collect all currency values after "Tanggal Lahir" ────────
        // PDF columns are extracted in order: Upah, JKK, JKM, JHT_TK, JHT_PJ,
        // Total, JP_PJ, JP_TK, Rapel, JKP, ... (each block = $count values)
        $tglLahirIdx = null;
        for ($i = 0; $i < $n; $i++) {
            if (stripos($lines[$i], 'tanggal lahir') !== false) { $tglLahirIdx = $i; break; }
        }

        $allNums = [];
        $startLine = ($tglLahirIdx ?? ($namaTKIdx ?? 0)) + 1;
        for ($i = $startLine; $i < $n; $i++) {
            if (preg_match('/^[\d,]+\.\d{2}$/', $lines[$i])) {
                $allNums[] = $this->toFloat($lines[$i]);
            }
        }

        $allNums = $this->filterGrandTotalNumbers($allNums, count($niks));

        // Post-filter safety net: jika outlier detection terlalu agresif
        // (mis. halaman terakhir hanya 2 karyawan sehingga threshold sangat rendah),
        // fallback ke range filter eksplisit agar kolom JHT yang besar tidak ikut dibuang.
        $expectedMin = count($niks) * 4; // minimal 4 kolom per karyawan
        if (count($niks) > 0 && count($allNums) < $expectedMin) {
            Log::warning('BPJS Parser: angka tidak cukup setelah filter, fallback ke range 5000-250000', [
                'filtered_count' => count($allNums),
                'expected_min'   => $expectedMin,
                'employee_count' => count($niks),
            ]);
            $allNums = array_values(array_filter(
                $allNums,
                fn($n) => $n >= 5000 && $n <= 250000
            ));
        }

        // Split into blocks of $count; map to iuran columns by position
        $blocks = array_chunk($allNums, $count);
        // [0]=Upah (skip), [1]=JKK, [2]=JKM, [3]=JHT_TK, [4]=JHT_PJ,
        // [5]=Total, [6]=JP_PJ, [7]=JP_TK, [8+]=Rapel/JKP (ignore)

        // ── Step 4b: Validate counts before assembly ──────────────────────────
        if (count($niks) === 0) {
            Log::warning('BPJS Parser: tidak ada NIK ditemukan di PDF', [
                'text_length' => strlen($text),
                'pdf_preview' => substr($text, 0, 500),
            ]);
            return [];
        }

        if (count($niks) !== count($names)) {
            Log::warning('BPJS Parser: jumlah NIK tidak sama dengan jumlah nama', [
                'nik_count'  => count($niks),
                'name_count' => count($names),
                'niks'       => $niks,
                'names'      => $names,
            ]);
            // Jangan return [] — lanjutkan tapi gunakan min() agar tidak out-of-bounds
            $count = min(count($niks), count($names));
        } else {
            $count = count($niks);
        }

        // Validasi total angka vs jumlah kolom yang diharapkan
        $total_numbers = array_sum(array_map('count', $blocks));
        if ($count > 0 && ($total_numbers % $count !== 0)) {
            Log::warning('BPJS Parser: total angka tidak habis dibagi jumlah karyawan', [
                'total_numbers'  => $total_numbers,
                'employee_count' => $count,
                'remainder'      => $total_numbers % $count,
            ]);
        }

        // ── Step 4c: Reindex arrays to avoid holes before index access ────────
        $niks  = array_values($niks);
        $names = array_values($names);
        foreach ($blocks as &$block) {
            $block = array_values($block);
        }
        unset($block);

        Log::debug('BPJS_DEBUG niks', ['data' => array_slice($niks, 0, 5)]);
        Log::debug('BPJS_DEBUG names', ['data' => array_slice($names, 0, 5)]);
        Log::debug('BPJS_DEBUG blocks_0', ['data' => array_slice($blocks[0] ?? [], 0, 5)]);

        // ── Step 5: Build rows ───────────────────────────────────────────────
        $rows = [];
        for ($idx = 0; $idx < $count; $idx++) {
            if (!isset($niks[$idx]) || !isset($names[$idx])) {
                Log::warning('BPJS Parser: idx out of bounds saat assembly', ['idx' => $idx]);
                continue;
            }

            $row = [
                'nik'                => $niks[$idx] ?? null,
                'no_peserta'         => $nosPeserta[$idx] ?? null,
                'nama'               => $names[$idx] ?? '',
                'iuran_jkk'          => (float) ($blocks[1][$idx] ?? 0),
                'iuran_jkm'          => (float) ($blocks[2][$idx] ?? 0),
                'iuran_jht_employee' => (float) ($blocks[3][$idx] ?? 0),
                'iuran_jht_employer' => (float) ($blocks[4][$idx] ?? 0),
                'iuran_jp_employer'  => (float) ($blocks[6][$idx] ?? 0),
                'iuran_jp_employee'  => (float) ($blocks[7][$idx] ?? 0),
                'total_iuran'        => 0.0,
            ];

            // JHT base = komponen yang selalu terbaca benar (blocks 1–4)
            $jhtBase = $row['iuran_jkk'] + $row['iuran_jkm']
                + $row['iuran_jht_employee'] + $row['iuran_jht_employer'];

            // JP yang valid selalu jauh < jhtBase (JP rate ~3%, JHT ~7.25% + JKK + JKM).
            // Jika jp >= jhtBase → terbaca dari kolom yang salah (mis. kolom Total
            // tersedot ke slot JP karena column-order PDF berbeda) → zero-kan.
            foreach (['iuran_jp_employee', 'iuran_jp_employer'] as $jpField) {
                if ($row[$jpField] > 0 && $row[$jpField] >= $jhtBase) {
                    Log::warning('BPJS Parser: ' . $jpField . ' >= total JHT — misalignment kolom, di-reset 0', [
                        'nik'      => $niks[$idx] ?? 'unknown',
                        'value'    => $row[$jpField],
                        'jht_base' => $jhtBase,
                        'idx'      => $idx,
                    ]);
                    $row[$jpField] = 0.0;
                }
            }

            $row['total_iuran'] = $jhtBase
                + $row['iuran_jp_employee']
                + $row['iuran_jp_employer'];

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Wrapped 2-3 line names in the PDF's "Nama Tenaga Kerja" column get
     * extracted as separate lines, inflating $names beyond $targetCount.
     * Each adjacent pair is scored on how likely line[i] is a *continuation*
     * of line[i-1] (rather than a new employee's name), and only the
     * highest-scoring `excess` positions are merged — picked by rank across
     * the whole page, not "first match wins". This avoids the old fallback
     * of blindly merging the last two names in the list, which could corrupt
     * completely unrelated rows when no confident fragment was found.
     */
    private function mergeFragmentedNames(array $names, int $targetCount): array
    {
        if (count($names) <= $targetCount) {
            return $names;
        }

        $excess = count($names) - $targetCount;

        Log::info('BPJS Parser: deteksi fragmentasi nama', [
            'raw_count'    => count($names),
            'target_count' => $targetCount,
            'excess'       => $excess,
            'raw_names'    => $names,
        ]);

        // Kata yang biasa muncul di tengah nama Indonesia
        // (entry sebelumnya diakhiri kata ini → entry berikutnya adalah fragmennya)
        $connectingWords = [
            'NUR', 'NOR', 'BIN', 'BINTI', 'AL', 'EL',
            'ABD', 'ABDUL', 'ABDUR', 'ABDU',
            'BUDI', 'TRI', 'DWI', 'EKA', 'NI',
            'SRI', 'AYU', 'DEWI', 'INDAH', 'SITI',
            'MUHAMMAD', 'MUHAMAD', 'MOCHAMAD', 'MOCHAMMAD',
            'MOHAMMAD', 'MOHAMAD', 'MOH', 'MOCH',
            'ACHMAD', 'AHMAD', 'AHMAT',
            'ANAK', 'PUTU', 'MADE', 'WAYAN', 'KETUT',
            'LUH', 'DESAK', 'SANG',
        ];

        $scores = [];
        for ($i = 1; $i < count($names); $i++) {
            $current       = trim($names[$i]);
            $previous      = trim($names[$i - 1]);
            $currentWords  = explode(' ', $current);
            $previousWords = explode(' ', $previous);

            $score = 0;

            // Fewer words on the continuation line = stronger signal, since
            // wrap fragments are usually just the tail end of a long name.
            $wordCount = count($currentWords);
            if ($wordCount === 1)      $score += 10;
            elseif ($wordCount === 2)  $score += 5;
            elseif ($wordCount === 3)  $score += 2;

            $lastWordOfPrevious = strtoupper(end($previousWords));
            if (in_array($lastWordOfPrevious, $connectingWords, true)) {
                $score += 8;
            }

            // Trailing single-letter token (middle initial like "M") is a
            // strong sign the line is a fragment, not a full standalone name.
            $lastWordOfCurrent = end($currentWords);
            if (mb_strlen($lastWordOfCurrent) <= 2) {
                $score += 3;
            }

            $scores[$i] = $score;
        }

        arsort($scores);
        $mergePositions = array_slice(array_keys($scores), 0, $excess);
        sort($mergePositions);

        Log::info('BPJS Parser: posisi fragment yang akan digabung', [
            'positions' => $mergePositions,
            'scores'    => array_intersect_key($scores, array_flip($mergePositions)),
        ]);

        // Merge from the highest index down so earlier splices don't shift
        // positions we haven't processed yet.
        $result = $names;
        foreach (array_reverse($mergePositions) as $pos) {
            $mergedName        = trim($result[$pos - 1] . ' ' . $result[$pos]);
            $result[$pos - 1]  = $mergedName;
            array_splice($result, $pos, 1);

            Log::info('BPJS Parser: merge fragment', [
                'position' => $pos,
                'result'   => $mergedName,
                'score'    => $scores[$pos] ?? null,
            ]);
        }

        if (count($result) !== $targetCount) {
            Log::warning('BPJS Parser: jumlah nama setelah merge masih tidak cocok, kemungkinan ada nama yang salah gabung — perlu review manual', [
                'result_count' => count($result),
                'target_count' => $targetCount,
                'result'       => $result,
            ]);
        }

        Log::info('BPJS Parser: nama setelah merge', [
            'count'  => count($result),
            'result' => $result,
        ]);

        return array_values($result);
    }

    private function filterGrandTotalNumbers(array $numbers, int $employeeCount): array
    {
        if ($employeeCount <= 0 || count($numbers) === 0) {
            return $numbers;
        }

        // STEP 1: Hitung median untuk referensi
        $sorted = $numbers;
        sort($sorted);
        $mid    = (int)(count($sorted) / 2);
        $median = $sorted[$mid] ?? 0;

        // STEP 2: Trim remainder dari akhir (logic lama)
        $total     = count($numbers);
        $remainder = $total % $employeeCount;
        $result    = $numbers;

        if ($remainder > 0 && $remainder < $employeeCount) {
            $trimmed       = array_slice($numbers, 0, $total - $remainder);
            $removedValues = array_slice($numbers, $total - $remainder);

            Log::info('BPJS Parser: trim grand total dari akhir', [
                'removed_count'  => $remainder,
                'removed_values' => $removedValues,
            ]);

            $result = array_values($trimmed);
        }

        // STEP 3: Deteksi dan hapus outlier (grand total tersisa) — HANYA kalau
        // jumlah angka masih belum rapi kelipatan employeeCount setelah STEP 2.
        // Kalau sudah rapi (kasus normal: cut-marker "Jumlah Seluruhnya" + trim
        // remainder di STEP 2 sudah cukup), JANGAN jalankan filter ini — median
        // dihitung campur dari kolom-kolom yang skalanya jauh berbeda (mis. Upah
        // ~3.5 juta vs JKM ~10 ribu), jadi threshold 0.3× bisa salah membuang
        // KOLOM YANG SAH SELURUHNYA (Upah, JHT Pemberi Kerja, Total Iuran semua
        // ikut terbuang), bukan cuma baris grand total — ini pernah kejadian
        // nyata di tagihan PT Imperial Prima Food dan merusak SEMUA baris
        // karyawan, bukan cuma yang di halaman terakhir.
        if ($median > 0 && count($result) % $employeeCount !== 0) {
            $threshold = $employeeCount * $median * 0.3;
            $before    = count($result);

            $result = array_values(
                array_filter(
                    $result,
                    fn($n) => $n < $threshold
                )
            );

            $removed = $before - count($result);
            if ($removed > 0) {
                Log::info('BPJS Parser: hapus outlier grand total', [
                    'removed_count' => $removed,
                    'threshold'     => $threshold,
                    'median'        => $median,
                    'employee_count'=> $employeeCount,
                ]);
            }
        }

        return $result;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function parsePeriode(string $text): ?string
    {
        // "2025-01" or "01-2025"
        if (preg_match('/(\d{4})-(\d{2})/', $text, $m)) return "{$m[1]}-{$m[2]}";
        if (preg_match('/(\d{2})-(\d{4})/', $text, $m)) return "{$m[2]}-{$m[1]}";
        if (preg_match('/(\d{2})\/(\d{4})/', $text, $m)) return "{$m[2]}-{$m[1]}";

        $monthMap = [
            'januari'=>'01','februari'=>'02','maret'=>'03','april'=>'04',
            'mei'=>'05','juni'=>'06','juli'=>'07','agustus'=>'08',
            'september'=>'09','oktober'=>'10','november'=>'11','desember'=>'12',
            'january'=>'01','february'=>'02','march'=>'03','may'=>'05',
            'june'=>'06','july'=>'07','august'=>'08','october'=>'10',
            'december'=>'12',
        ];
        $lower = strtolower($text);
        foreach ($monthMap as $word => $num) {
            if (str_contains($lower, $word)) {
                if (preg_match('/(\d{4})/', $text, $m)) {
                    return "{$m[1]}-{$num}";
                }
            }
        }
        return null;
    }

    private function cleanNik(mixed $val): ?string
    {
        if (empty($val)) return null;
        $cleaned = preg_replace('/\D/', '', (string) $val);
        return strlen($cleaned) >= 10 ? $cleaned : null;
    }

    private function toFloat(mixed $val): float
    {
        if (is_null($val) || $val === '' || $val === '-') return 0.0;
        $str = str_replace(' ', '', trim((string) $val));
        if ($str === '') return 0.0;

        $lastDot   = strrpos($str, '.');
        $lastComma = strrpos($str, ',');

        if ($lastDot !== false && $lastComma !== false) {
            if ($lastDot > $lastComma) {
                // "228,900.00" — US format: comma=thousands, dot=decimal
                $str = str_replace(',', '', $str);
            } else {
                // "228.900,00" — Indonesian format: dot=thousands, comma=decimal
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            }
        } elseif ($lastComma !== false) {
            $afterComma = substr($str, $lastComma + 1);
            if (strlen($afterComma) <= 2) {
                $str = str_replace(',', '.', $str); // decimal comma
            } else {
                $str = str_replace(',', '', $str);   // thousands comma
            }
        }
        // Only dots or no separator: use as-is

        return (float) preg_replace('/[^\d.]/', '', $str);
    }
}
