<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Models\Outlet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOutletLegalEntitiesFromListCommand extends Command
{
    protected $signature = 'outlets:backfill-legal-entities-from-list {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Isi outlets.legal_entity_id dari daftar mapping Outlet-PT yang diberikan pimpinan (satu kali jalan, datanya di-hardcode di command ini)';

    /**
     * [nama outlet => nama PT/CV/perorangan] persis dari file "NAMA CV.PT ALL
     * OUTLET.xlsx" yang diberikan user. Nama kosong = belum ada PT tercatat
     * untuk outlet tsb, dilewati (bukan error).
     */
    private const MAPPING = [
        'DAPUR PENANG GRAND CITY' => 'CV. SENTOSA PRIMA FOOD',
        'DAPUR PENANG TUNJUNGAN PLAZA' => 'CV. SENTOSA PRIMA FOOD',
        'FOOD SQUARE BANJAR' => 'PT. PRIMA BOGA SURYA BANJAR',
        'AH PEK MERR' => 'PT MANDIRI KOPIJAYA MAKMUR',
        'AH PEK GRESSMALL' => 'PT MAKMUR GEMILANG KOPIJAYA',
        'AH PEK TUNJUNGAN' => 'PT MAKMUR GEMILANG KOPIJAYA',
        'AH PEK BINTARO' => 'PT MAQHA MITRA KOPIJAYA',
        'AH PEK BENHILL' => 'PT SAYAP MANDIRI KOPITIAM',
        'AH PEK TANJUNG DUREN' => 'PT SAYAP MANDIRI KOPITIAM',
        'AH PEK SAMARINDA' => 'PT SUBUR MAKMUR BOGATAMA',
        'AH PEK PARK SHANGHAI' => 'PT MAKMUR GEMILANG KOPIJAYA',
        'AH PEK KUPANG INDAH' => 'CV MULTIRASA KOPIJAYA',
        'AH PEK MALANG' => 'UD PERDANA FOOD',
        'AH PEK MATARAM' => 'CV LOMBOK KOPITIAM MANDIRI',
        'AH PEK MARGONDA DEPOK' => 'PT SAYAP MANDIRI KOPITIAM',
        'AH PEK JAMBI' => '',
        'BE ON 3 CIPUTRA WORLD' => 'CV MULTIRASA KOPI JAYA',
        'DIMSUM PRODUCTION' => 'PT BERJAYA LANCAR TERUS',
        'KAMPUNG MELAYU BUNG HATTA LOMBOK' => 'CV IKI LAKONE LOMBOK',
        'KAMPUNG MELAYU LOMBOK' => 'CV LOMBOK KAMPONG JAYA',
        'KULTUR HAUS MAKASSAR' => 'CV. HEXA MITRA BOGA',
        'KINGSMAN LOMBOK' => 'CV. BRILLIANT MULTI USAHA',
        'MALAY VILLAGE CILANDAK TOWN SQUARE' => 'PT. MANUNGGAL PRIMA FOOD',
        'MALAY VILLAGE FOOD SQUARE BANJAR' => 'PT. JAYA MITRA PRIMA BOGA',
        'MALAY VILLAGE LIVING WORLD' => 'PT. MANUNGGAL PRIMA FOOD',
        'MALAY VILLAGE SUPERMALL PAKUWON INDAH' => 'CV. BERJAYA PRIMA FOOD',
        'MALAY VILLAGE SUTOS' => 'CV. BERJAYA PRIMA FOOD',
        'MY KOPI - O! BALCONY CITY BALIKPAPAN' => 'ARDIANSYAH MUCHSIN',
        'MY KOPI - O! FOOD SQUARE BANJAR' => 'PT. JAYA MITRA PRIMA BOGA',
        'MY KOPI - O! LIVING WORLD TANGERANG' => 'PT. GRAHA KOPI JAYA',
        'MY KOPI - O! MX MALANG' => 'CV. KOPIJAYA PRIMA FOOD MALANG',
        'MY KOPI - O! RAJAWALI MALL PALEMBANG' => 'CV SINAR INTI MANIS',
        'MY KOPI - O! TOWN SUITE BALI' => 'PT. GRAHA KOPI JAYA',
        'MY KOPI - O! BEKASI' => 'CV. GITA DJOEANG SEJAHTERA',
        'MY KOPI - O! CILANDAK TOWN SQUARE JAKARTA' => 'PT. GRAHA KOPI JAYA',
        'MY KOPI - O! CIPUTRA WORLD SURABAYA' => 'CV. KOPIJAYA PRIMA FOOD',
        'MY KOPI - O! GRAND CITY' => 'CV ADICIPTA PRIMA FOOD',
        'MY KOPI - O! INDONESIAN FOOD CANDRAKIRANA' => 'CV KIRANA INDO KOPIJAYA',
        'MY KOPI - O! INDONESIAN FOOD GAJAYANA + CATERING MALANG' => 'UD. PERDANA FOOD',
        'MY KOPI - O! KUPANG' => 'CV. TAMAN LAUT INDAH',
        'MY KOPI - O! LAMPUNG' => 'CV. LESTARI BERSAMA JAYA',
        'MY KOPI - O! LOMBOK' => 'CV LOMBOK KOPI JAYA',
        'MY KOPI - O! MALL RATU INDAH MAKASSAR' => 'CV PENTA MITRA',
        'MY KOPI - O! NIPAH MALL' => 'CV PENTA MITRA',
        'MY KOPI - O! PAKUWON MALL' => 'CV BERJAYA MY KOPI O!',
        'MY KOPI - O! PALU' => 'CV SAMA SUBUR',
        'MY KOPI - O! SAMARINDA' => 'PT SUBUR MAKMUR BOGATAMA',
        'MY KOPI - O! SEMARANG' => 'CV MITRA BOGA SELARAS',
        'MY KOPI - O! THE PARK SOLO' => 'PT BINTANG GLORIA',
        'MY KOPI - O! TRANS STUDIO MALL MAKASSAR' => 'CV PENTA MITRA',
        'MY KOPI O! HEAD OFFICE' => 'MY KOPI-O! GROUP',
        'MY KOPI - O! SERANG' => 'PT GALERI RUMAH CAPUNG',
        'MY KOPI - O! HHB BANDUNG' => 'CV SUKSES INTAN PANGAN',
        'PENANGS HOUSE GALAXY MALL' => 'CV. SAMUDRA PRIMA FOOD',
        'PENANGS HOUSE TUNJUNGAN PLAZA' => 'CV. SENTOSA PRIMA FOOD',
        'O! CAFÃ BE ON 3 CWS' => 'CV MULTIRASA KOPI JAYA',
        'PRODUCTION JAKARTA' => 'PT. BERJAYA LANCAR TERUS',
        'PRODUCTION PENANGS SURABAYA' => 'PT. BERJAYA LANCAR TERUS',
        'PRODUCTION QUALI SURABAYA' => 'CV IMPERIAL PRIMA FOOD',
        'PRODUCTION QUALI SURABAYA 2' => 'CV IMPERIAL CIPTA KARYA',
        'PRODUCTION QUALI SURABAYA 3' => 'CV IMPERIAL SELARAS RASA',
        'TOKIO-O! MANDALA MALANG' => 'PT MAKMUR RAMENJAYA MANDIRI',
        'QUALI GALAXY MALL' => 'PT IMPERIAL PRIMA FOOD',
        'QUALI HARTONO MALL JOGJAKARTA / PAKUWON MALL JOGJA' => 'CV HARTONO PRIMA FOOD',
        'QUALI JOGJA CITY MALL' => 'PT PRIMA MANUNGGAL SEJAHTERA',
        'QUALI LOMBOK EPICENTRUM MALL' => 'CV BRILLIANT INTI FOOD',
        'QUALI PLAZA AMBARUKMO' => 'CV. WINDU MAKMUR LESTARI',
        'QUALI TUNJUNGAN PLAZA' => 'CV ADICIPTA PRIMA FOOD',
        'QUALI SUPERMALL/PAKUWON' => 'PT IMPERIAL PRIMA FOOD',
        'QUALI ESPLANADE JEMBER' => 'CV MAPAN SEJAHTERA FOOD',
        'QUALI ROYAL PLAZA' => 'CV ROYAL PRIMA FOOD',
        'QUALI RAJAWALI MALL PALEMBANG' => 'CV SINAR INTI MANIS',
        'QUALI SOLO SQUARE' => 'AGUS PRIYANTO SUMANTO',
        'QUALI SURABAYA TOWN SQUARE' => 'CV TOWN SQUARE PRIMA FOOD',
        'QUALI MANADO TOWN SQUARE' => 'PT SELERA ANEKA RASA',
        'QUALI PARIS VAN JAVA' => 'AUDRIE ABED DARMAWAN',
        'OFFICE ( BRANDING, DESIGN, JOBSTREET, KAR BUNGA, PETCASH DH)' => 'CV KREASI KOPI JAYA',
        'OFFICE ( BRANDING, DESIGN, JOBSTREET )' => 'CV CITARASA KOPI JAYA',
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $this->info(($isDryRun ? '[DRY RUN] ' : '') . 'Memproses ' . count(self::MAPPING) . ' baris mapping outlet-PT...');

        $updated = 0;
        $alreadySet = 0;
        $outletNotFound = [];
        $legalEntityNotFound = [];
        $blankCompany = [];

        DB::beginTransaction();

        try {
            foreach (self::MAPPING as $outletName => $companyName) {
                $outletName = trim($outletName);
                $companyName = trim($companyName);

                $outlet = Outlet::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($outletName)])->first();

                if (! $outlet) {
                    $outletNotFound[] = $outletName;
                    continue;
                }

                if ($companyName === '') {
                    $blankCompany[] = $outletName;
                    continue;
                }

                $legalEntity = LegalEntity::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($companyName)])->first();

                if (! $legalEntity) {
                    $legalEntityNotFound[] = "{$companyName}  (untuk outlet: {$outletName})";
                    continue;
                }

                if ((int) $outlet->legal_entity_id === (int) $legalEntity->id) {
                    $alreadySet++;
                    continue;
                }

                $this->line("  {$outletName}: legal_entity_id " . ($outlet->legal_entity_id ?? 'NULL') . " -> {$legalEntity->id} ({$legalEntity->name})");

                if (! $isDryRun) {
                    $outlet->update(['legal_entity_id' => $legalEntity->id]);
                }

                $updated++;
            }

            if ($isDryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal: ' . $e->getMessage());

            return 1;
        }

        $this->newLine();
        $this->info("Diupdate: {$updated} | Sudah benar sebelumnya: {$alreadySet}");

        if (! empty($outletNotFound)) {
            $this->warn('Outlet TIDAK ditemukan di Master Outlet (' . count($outletNotFound) . '), cek nama persis:');
            foreach ($outletNotFound as $name) {
                $this->line("  - {$name}");
            }
        }

        if (! empty($legalEntityNotFound)) {
            $this->warn('PT/CV TIDAK ditemukan di Master Legal Entity (' . count($legalEntityNotFound) . '), perlu dibuat manual dulu:');
            foreach ($legalEntityNotFound as $name) {
                $this->line("  - {$name}");
            }
        }

        if (! empty($blankCompany)) {
            $this->line('Outlet tanpa PT di daftar sumber (' . count($blankCompany) . '), dilewati: ' . implode(', ', $blankCompany));
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return 0;
    }
}
