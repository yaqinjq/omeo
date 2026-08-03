<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Models\Outlet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOutletLegalEntitiesFromListCommand extends Command
{
    protected $signature = 'outlets:backfill-legal-entities-from-list {--dry-run : Preview tanpa menyimpan}';

    protected $description = 'Isi outlets.legal_entity_id berdasarkan outlet_id yang sudah dicocokkan manual dari daftar PT-Outlet pimpinan (bukan cocok nama otomatis — nama outlet di sistem beda format dari daftar sumber)';

    /**
     * [outlet_id => nama PT/CV] — HANYA yang sudah dicocokkan dengan yakin
     * terhadap isi tabel outlets saat ini (dicek manual satu-satu terhadap
     * daftar lengkap 57 outlet, bukan pencocokan nama otomatis, karena nama
     * di sistem beda format signifikan dari daftar sumber, mis. "AH PEK
     * BENHILL" di daftar sumber = "AH PEK KOPITIAM BENDUNGAN HILIR" di
     * sistem). Outlet yang ambigu atau belum ada di Master Outlet TIDAK
     * dimasukkan di sini — dicek manual lewat form Master Outlet.
     */
    private const MAPPING = [
        2  => 'PT MAQHA MITRA KOPIJAYA',           // AH PEK KOPITIAM BINTARO
        3  => 'PT SAYAP MANDIRI KOPITIAM',         // AH PEK KOPITIAM BENDUNGAN HILIR
        4  => 'PT SAYAP MANDIRI KOPITIAM',         // AH PEK KOPITIAM MARGONDA
        5  => 'PT SAYAP MANDIRI KOPITIAM',         // AH PEK TANJUNG DUREN
        6  => 'CV LOMBOK KOPITIAM MANDIRI',        // AH PEK KOPITIAM MATARAM
        7  => 'PT SUBUR MAKMUR BOGATAMA',          // AHPEK KOPITIAM SAMARINDA
        9  => 'PT MAKMUR GEMILANG KOPIJAYA',       // AH PEK TUNJUNGAN
        10 => 'CV MULTIRASA KOPIJAYA',             // AH PEK KUPANG INDAH
        11 => 'PT MANDIRI KOPIJAYA MAKMUR',        // AH PEK KOPITIAM MERR 221
        12 => 'PT MAKMUR GEMILANG KOPIJAYA',       // AH PEK GRESS MALL
        14 => 'CV MULTIRASA KOPI JAYA',            // BE ON 3
        17 => 'CV. HEXA MITRA BOGA',               // KULTUR HAUS
        19 => 'CV PENTA MITRA',                    // MY KOPI O! TRANS STUDIO MALL
        20 => 'CV PENTA MITRA',                    // MY KOPI O! MALL RATU INDAH
        21 => 'PT GALERI RUMAH CAPUNG',            // MY KOPI-O! SERANG
        22 => 'CV SAMA SUBUR',                     // MY KOPI-O! PALU
        23 => 'CV BERJAYA MY KOPI O!',             // MY KOPI-O! PAKUWON MALL
        25 => 'CV ADICIPTA PRIMA FOOD',            // MY KOPI O GRAND CITY
        27 => 'CV. KOPIJAYA PRIMA FOOD',           // MY KOPI O CIPUTRA WORLD
        28 => 'CV. GITA DJOEANG SEJAHTERA',        // MY KOPI-O! BEKASI
        29 => 'CV. TAMAN LAUT INDAH',              // MY KOPI-O! KUPANG
        31 => 'CV PENTA MITRA',                    // MY KOPI O! NIPAH
        32 => 'CV. LESTARI BERSAMA JAYA',          // MY KOPI O! LAMPUNG
        33 => 'CV LOMBOK KOPI JAYA',               // MY KOPI O EPICENTRUM LOMBOK
        34 => 'PT. GRAHA KOPI JAYA',               // MY KOPI O! CILANDAK TOWN SQUARE
        37 => 'CV MAPAN SEJAHTERA FOOD',           // QUA-LI JEMBER
        39 => 'CV BRILLIANT INTI FOOD',            // QUA-LI LOMBOK
        41 => 'CV ADICIPTA PRIMA FOOD',            // QUA-LI TUNJUNGAN PLAZA
        42 => 'PT IMPERIAL PRIMA FOOD',            // QUA-LI GALAXY MALL
        43 => 'CV. WINDU MAKMUR LESTARI',          // QUA-LI AMBARUKMO PLAZA
        45 => 'PT. BERJAYA LANCAR TERUS',          // Production Penangs Surabaya
        46 => 'PT BERJAYA LANCAR TERUS',           // Dimsum Production
        47 => 'CV IMPERIAL PRIMA FOOD',            // Production Quali Surabaya
        48 => 'CV IMPERIAL CIPTA KARYA',           // Production Quali Surabaya 2
        49 => 'CV IMPERIAL SELARAS RASA',          // Production Quali Surabaya 3
        57 => 'PT PRIMA MANUNGGAL SEJAHTERA',      // QUA-LI JOGJA CITY MALL
    ];

    /**
     * Outlet dari sistem yang TIDAK ada di daftar sumber pimpinan sama
     * sekali (brand DAPUR PENANG, MALAY VILLAGE, PENANGS HOUSE, cabang kota
     * lain) atau namanya beda jauh sehingga tidak yakin itu outlet yang
     * sama (mis. "QUALI SOLO SQUARE" vs "MY KOPI O SOLO" di sistem) — perlu
     * dicek pimpinan lalu diisi manual lewat Master Outlet.
     */
    private const NEEDS_MANUAL_REVIEW = [
        1  => 'OFFICE KK — tidak ada di daftar sumber',
        8  => 'AH PEK KOPITIAM PAKUWON CITY MALL (PCM) — tidak ada di daftar sumber',
        13 => 'AH PEK SEMERU — tidak ada di daftar sumber',
        15 => 'KAMPUNG MELAYU — daftar sumber ada 2 varian (Bung Hatta / Lombok biasa), tidak jelas ini yang mana',
        16 => 'KAMPUNG MELAYU EPICENTRUM LOMBOK — sama, ambigu dengan id 15',
        18 => 'MY KOPI O SOLO — di daftar sumber ada "MY KOPI-O! THE PARK SOLO" beda nama, cek dulu apa outlet yang sama',
        24 => 'MY KOPI-O! HAY HOTEL BANDUNG — di daftar sumber ada "MY KOPI-O! HHB BANDUNG", kemungkinan sama tapi cek dulu',
        26 => 'MY KOPI-O! CITY CENTRUM MALL — tidak ada di daftar sumber',
        30 => 'MY KOPI O! MALANG (MLG) — di daftar sumber ada "MY KOPI-O! MX MALANG", kemungkinan sama tapi cek dulu',
        35 => 'MY KOPI O INDONESIAN BISTRO YOGYAKARTA — tidak ada di daftar sumber',
        36 => 'MY KOPI O INDONESIA BISTRO SEMARANG — di daftar sumber ada "MY KOPI-O! SEMARANG", kemungkinan sama tapi cek dulu',
        38 => 'QUA-LI PAKUWON MALL (JOGJA) — di daftar sumber ada "QUALI HARTONO MALL JOGJAKARTA", kemungkinan sama tapi cek dulu',
        40 => 'QUA-LI PAKUWON MALL (SBY) — di daftar sumber ada "QUALI SUPERMALL/PAKUWON", kemungkinan sama tapi cek dulu',
        44 => 'TOKIO O JAPANASE CAFE — di daftar sumber ada "TOKIO-O! MANDALA MALANG" beda nama, cek dulu apa outlet yang sama',
        50 => 'Kreasi Kopi Jaya — nama outlet ini SAMA PERSIS dengan nama PT "CV KREASI KOPI JAYA" di daftar sumber, kemungkinan outlet office yang PT-nya sendiri, cek dulu',
        51 => 'Kreasi Kopi Tiam — tidak ada padanan jelas di daftar sumber',
        52 => 'Berjasa Kopi Jaya — tidak ada padanan jelas di daftar sumber',
        53 => 'Production Multirasa Kopi Jaya — tidak ada padanan jelas di daftar sumber',
        54 => 'IKIWAE PLAY SEMERU MALANG — tidak ada di daftar sumber',
        55 => 'KINGSMAN MATARAM — di daftar sumber ada "KINGSMAN LOMBOK" beda nama, kemungkinan sama (Mataram = ibu kota Lombok) tapi cek dulu',
        56 => 'PRODUCTION MATARAM — tidak ada padanan jelas di daftar sumber',
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $this->info(($isDryRun ? '[DRY RUN] ' : '') . 'Memproses ' . count(self::MAPPING) . ' outlet yang sudah dicocokkan manual...');

        $updated = 0;
        $alreadySet = 0;
        $outletNotFound = [];
        $legalEntityCreated = 0;

        DB::beginTransaction();

        try {
            foreach (self::MAPPING as $outletId => $companyName) {
                $outlet = Outlet::find($outletId);

                if (! $outlet) {
                    $outletNotFound[] = "id={$outletId} ({$companyName})";
                    continue;
                }

                $companyName = trim($companyName);
                $legalEntity = LegalEntity::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($companyName)])->first();

                if (! $legalEntity) {
                    $entityType = 'Lainnya';
                    if (preg_match('/^PT\.?\s/i', $companyName)) $entityType = 'PT';
                    elseif (preg_match('/^CV\.?\s/i', $companyName)) $entityType = 'CV';
                    elseif (preg_match('/^UD\.?\s/i', $companyName)) $entityType = 'UD';

                    $this->line("  BUAT PT/CV baru: {$companyName} ({$entityType})");

                    if (! $isDryRun) {
                        $legalEntity = LegalEntity::create([
                            'name' => $companyName,
                            'entity_type' => $entityType,
                            'is_active' => true,
                        ]);
                    }
                    $legalEntityCreated++;
                }

                if ($isDryRun) {
                    // legalEntity mungkin belum benar-benar ada (dry-run tidak
                    // create) — lewati perbandingan id, cukup tampilkan rencana.
                    $this->line("  {$outlet->name} (id={$outletId}): -> {$companyName}");
                    $updated++;
                    continue;
                }

                if ((int) $outlet->legal_entity_id === (int) $legalEntity->id) {
                    $alreadySet++;
                    continue;
                }

                $this->line("  {$outlet->name} (id={$outletId}): legal_entity_id " . ($outlet->legal_entity_id ?? 'NULL') . " -> {$legalEntity->id} ({$legalEntity->name})");
                $outlet->update(['legal_entity_id' => $legalEntity->id]);
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
        $this->info("Diupdate: {$updated} | Sudah benar sebelumnya: {$alreadySet} | PT/CV baru dibuat: {$legalEntityCreated}");

        if (! empty($outletNotFound)) {
            $this->warn('Outlet ID tidak ditemukan (' . count($outletNotFound) . '):');
            foreach ($outletNotFound as $name) {
                $this->line("  - {$name}");
            }
        }

        $this->newLine();
        $this->warn(count(self::NEEDS_MANUAL_REVIEW) . ' outlet PERLU DICEK MANUAL oleh pimpinan (isi lewat Master Outlet setelah dipastikan PT-nya):');
        foreach (self::NEEDS_MANUAL_REVIEW as $outletId => $note) {
            $this->line("  - id={$outletId}: {$note}");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->warn('[DRY RUN] Tidak ada yang disimpan. Jalankan tanpa --dry-run untuk eksekusi.');
        }

        return 0;
    }
}
