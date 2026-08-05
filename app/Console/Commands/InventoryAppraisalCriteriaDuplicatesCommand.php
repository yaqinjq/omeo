<?php

namespace App\Console\Commands;

use App\Models\AppraisalIndicator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventoryAppraisalCriteriaDuplicatesCommand extends Command
{
    protected $signature = 'appraisal:inventory-criteria-duplicates';

    protected $description = 'Laporan read-only (tidak mengubah data apa pun): daftar semua template kriteria appraisal + kriteria di dalamnya, dan kandidat duplikat lintas-template berdasarkan kemiripan teks kategori+pertanyaan. Dipakai untuk konfirmasi manual sebelum penggabungan/penghapusan.';

    public function handle(): int
    {
        $templates = DB::table('appraisal_criteria_templates')->orderBy('id')->get();

        if ($templates->isEmpty()) {
            $this->warn('Tidak ada appraisal_criteria_templates sama sekali.');
            return 0;
        }

        $this->info('=== DAFTAR TEMPLATE ===');
        foreach ($templates as $t) {
            $this->line("  #{$t->id} \"{$t->name}\" | lokasi_kerja=" . ($t->lokasi_kerja ?? 'NULL (semua kategori)') . ' | default=' . ($t->is_default ? 'ya' : 'tidak') . ' | aktif=' . ($t->is_active ? 'ya' : 'tidak'));
        }
        $this->newLine();

        $indicators = AppraisalIndicator::orderBy('template_id')->orderBy('category')->orderBy('id')->get();

        $this->info("=== TOTAL KRITERIA: {$indicators->count()} ===");
        $byTemplate = $indicators->groupBy(fn ($i) => $i->template_id ?? 'NULL');
        foreach ($byTemplate as $templateId => $items) {
            $templateName = $templateId === 'NULL'
                ? 'TANPA TEMPLATE (legacy)'
                : ($templates->firstWhere('id', (int) $templateId)->name ?? "template #{$templateId} (tidak ditemukan)");
            $this->line("  Template \"{$templateName}\": {$items->count()} kriteria");
        }
        $this->newLine();

        // Pemakaian: berapa appraisal_details menunjuk ke tiap indicator —
        // dipakai untuk menilai risiko sebelum sebuah kriteria digabung/dihapus.
        $usageCounts = DB::table('appraisal_details')
            ->select('appraisal_indicator_id', DB::raw('count(*) as cnt'))
            ->groupBy('appraisal_indicator_id')
            ->pluck('cnt', 'appraisal_indicator_id');

        // Kandidat duplikat: normalisasi "kategori|pertanyaan" lalu kelompokkan.
        // Hanya dianggap kandidat duplikat kalau muncul di LEBIH DARI SATU
        // template berbeda (kalau cuma duplikat di template yang sama, itu
        // bukan masalah lintas-template yang dikeluhkan HRD).
        $groups = $indicators->groupBy(fn ($i) => $this->normalize($i->category . '|' . $i->question));

        $duplicateGroups = $groups->filter(function ($items) {
            return $items->pluck('template_id')->unique()->count() > 1;
        });

        if ($duplicateGroups->isEmpty()) {
            $this->info('Tidak ada kandidat duplikat lintas-template yang terdeteksi (berdasarkan kemiripan teks kategori+pertanyaan).');
            $this->warn('Catatan: deteksi ini berbasis teks — kriteria yang maknanya sama tapi ditulis beda kata TIDAK akan terdeteksi otomatis. Tinjau juga daftar lengkap di atas secara manual.');
            return 0;
        }

        $this->info('=== KANDIDAT DUPLIKAT LINTAS-TEMPLATE (' . $duplicateGroups->count() . ' grup) ===');
        $this->warn('Tidak ada yang dihapus/digabung otomatis oleh command ini — ini murni laporan untuk keputusan Anda.');
        $this->newLine();

        $groupNo = 1;
        foreach ($duplicateGroups as $normalized => $items) {
            $this->line("Grup #{$groupNo}: \"{$normalized}\"");
            foreach ($items->sortBy('id') as $ind) {
                $templateName = $ind->template_id
                    ? ($templates->firstWhere('id', $ind->template_id)->name ?? "template #{$ind->template_id}")
                    : 'TANPA TEMPLATE';
                $usage = $usageCounts[$ind->id] ?? 0;
                $this->line("    id={$ind->id} | template=\"{$templateName}\" | kategori=\"{$ind->category}\" | pertanyaan=\"{$ind->question}\" | dipakai di {$usage} appraisal_details");
            }
            $this->newLine();
            $groupNo++;
        }

        $this->warn('Untuk tiap grup di atas: konfirmasi kriteria mana yang jadi "survivor" (biasanya yang dipakai di paling banyak appraisal_details), sisanya akan di-repoint lalu dihapus pada langkah berikutnya.');

        return 0;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s|]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
