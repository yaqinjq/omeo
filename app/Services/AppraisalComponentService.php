<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\AppraisalComponentScore;
use App\Models\AppraisalWeightConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppraisalComponentService
{
    public function buildForAppraisal(Appraisal $appraisal): array
    {
        $appraisal->loadMissing('period');
        $w = AppraisalWeightConfig::loadFor($appraisal->period?->type)->toWeightsArray();

        $rows = [];

        if ($appraisal->enable_kpi_component !== false) {
            $rows[] = $this->kpiComponent($appraisal, $w['kpi']);
        }

        $rows[] = $this->trainingComponent($appraisal, $w['training']);

        if ($appraisal->enable_skill_component === true) {
            $rows[] = $this->skillComponent($appraisal, $w['skill']);
        }

        if ($appraisal->enable_position_component === true) {
            $rows[] = $this->positionComponent($appraisal, $w['position']);
        }

        return $rows;
    }

    private function kpiComponent(Appraisal $appraisal, float $weight = 30.0): array
    {
        $existing = Schema::hasTable('appraisal_component_scores')
            ? DB::table('appraisal_component_scores')
                ->where('appraisal_id', $appraisal->id)
                ->where('component_key', 'kpi')
                ->first()
            : null;

        return [
            'key'              => 'kpi',
            'component_key'    => 'kpi',
            'component_label'  => 'KPI — Pencapaian Target Kerja',
            'label'            => 'KPI — Pencapaian Target Kerja',
            'source_type'      => 'manual_kpi',
            'weight'           => $weight,
            'score_raw'        => isset($existing->score_raw) ? (float) $existing->score_raw : null,
            'score_normalized' => isset($existing->score_normalized) ? (float) $existing->score_normalized : null,
            'notes'            => $existing->notes ?? null,
            'description'      => 'Masukkan persentase pencapaian KPI karyawan (0-100). Contoh: target 100%, tercapai 85% → isi 85.',
            'input_type'       => 'percentage',
            'suggested_badge'  => 'Manual',
        ];
    }

    private function trainingComponent(Appraisal $appraisal, float $weight = 20.0): array
    {
        $existing = Schema::hasTable('appraisal_component_scores')
            ? DB::table('appraisal_component_scores')
                ->where('appraisal_id', $appraisal->id)
                ->where('component_key', 'training')
                ->first()
            : null;

        $entries = [];
        if ($existing && $existing->payload) {
            $payload = json_decode($existing->payload, true);
            $entries = $payload['entries'] ?? [];
        }

        if (empty($entries) && (int) $appraisal->employee_id > 0) {
            try {
                if (Schema::hasTable('training_material_progress') && Schema::hasTable('training_materials')) {
                    $completed = DB::table('training_material_progress as tmp')
                        ->join('training_materials as tm', 'tm.id', '=', 'tmp.training_material_id')
                        ->where('tmp.employee_id', $appraisal->employee_id)
                        ->where('tmp.status', 'completed')
                        ->whereNotNull('tmp.posttest_score')
                        ->select('tm.title', 'tmp.pretest_score', 'tmp.posttest_score')
                        ->get();

                    foreach ($completed as $m) {
                        $pre  = (float) ($m->pretest_score ?? 0);
                        $post = (float) ($m->posttest_score ?? 0);
                        $avg  = $pre > 0 ? ($pre + $post) / 2 : $post;
                        $entries[] = [
                            'nama_materi' => $m->title,
                            'nilai'       => round($avg, 1),
                            'source'      => 'system',
                        ];
                    }
                }
            } catch (\Throwable) {
                // silent fail
            }
        }

        $systemAvg = count($entries) > 0
            ? round(collect($entries)->avg('nilai'), 2)
            : null;

        return [
            'key'              => 'training',
            'component_key'    => 'training',
            'component_label'  => 'Hasil Training',
            'label'            => 'Hasil Training',
            'source_type'      => 'training_multi',
            'weight'           => $weight,
            'score_raw'        => isset($existing->score_raw) ? (float) $existing->score_raw : $systemAvg,
            'score_normalized' => isset($existing->score_normalized) ? (float) $existing->score_normalized : $systemAvg,
            'entries'          => $entries,
            'input_type'       => 'training_multi',
            'notes'            => $existing->notes ?? null,
            'suggested_badge'  => count($entries) > 0 ? 'Dari sistem' : 'Manual',
        ];
    }

    private function skillComponent(Appraisal $appraisal, float $weight = 0.0): array
    {
        $existing = Schema::hasTable('appraisal_component_scores')
            ? AppraisalComponentScore::query()
                ->where('appraisal_id', $appraisal->id)
                ->where('component_key', 'competency_skill')
                ->first()
            : null;

        return [
            'key'              => 'competency_skill',
            'component_key'    => 'competency_skill',
            'component_label'  => 'Kompetensi Keahlian',
            'label'            => 'Kompetensi Keahlian',
            'source_type'      => $existing?->source_type ?? 'manual_placeholder',
            'weight'           => $weight,
            'score_raw'        => $existing?->score_raw !== null ? (float) $existing->score_raw : null,
            'score_normalized' => $existing?->score_normalized !== null ? (float) $existing->score_normalized : null,
            'notes'            => $existing?->notes ?? 'Diisi manual berdasarkan penilaian kompetensi keahlian karyawan.',
            'entries'          => [],
            'input_type'       => 'manual',
            'suggested_badge'  => 'Manual',
        ];
    }

    private function positionComponent(Appraisal $appraisal, float $weight = 0.0): array
    {
        $existing = Schema::hasTable('appraisal_component_scores')
            ? AppraisalComponentScore::query()
                ->where('appraisal_id', $appraisal->id)
                ->where('component_key', 'competency_position')
                ->first()
            : null;

        return [
            'key'              => 'competency_position',
            'component_key'    => 'competency_position',
            'component_label'  => 'Kompetensi Posisi / Jabatan',
            'label'            => 'Kompetensi Posisi / Jabatan',
            'source_type'      => $existing?->source_type ?? 'manual_placeholder',
            'weight'           => $weight,
            'score_raw'        => $existing?->score_raw !== null ? (float) $existing->score_raw : null,
            'score_normalized' => $existing?->score_normalized !== null ? (float) $existing->score_normalized : null,
            'notes'            => $existing?->notes ?? 'Diisi manual berdasarkan kompetensi posisi/jabatan yang dijalankan karyawan.',
            'entries'          => [],
            'input_type'       => 'manual',
            'suggested_badge'  => 'Manual',
        ];
    }

    public function syncForAppraisal(Appraisal $appraisal, array $components): array
    {
        $manualKeys = [
            'competency_skill'     => 'Kompetensi Keahlian',
            'competency_position'  => 'Kompetensi Posisi / Jabatan',
        ];
        $saved = [];

        foreach ($manualKeys as $key => $label) {
            $input = (array) ($components[$key] ?? []);
            if (empty($input)) {
                continue;
            }

            $record = AppraisalComponentScore::query()->updateOrCreate(
                ['appraisal_id' => $appraisal->id, 'component_key' => $key],
                [
                    'component_label'  => $label,
                    'source_type'      => 'manual_placeholder',
                    'score_raw'        => $this->normalizeNullableNumber($input['score_raw'] ?? null),
                    'score_normalized' => $this->normalizeNullableNumber($input['score_normalized'] ?? null),
                    'weight'           => (float) ($input['weight'] ?? 0),
                    'notes'            => $this->normalizeNullableString($input['notes'] ?? null),
                    'payload'          => null,
                ]
            );

            $saved[] = $record;
        }

        return $saved;
    }

    public function computeFinalScore(Appraisal $appraisal): ?float
    {
        if (! Schema::hasTable('appraisal_component_scores')) {
            return null;
        }

        $components  = AppraisalComponentScore::query()->where('appraisal_id', $appraisal->id)->get();
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($components as $component) {
            if ($component->score_normalized === null) {
                continue;
            }

            $weight = (float) $component->weight;
            if ($weight <= 0) {
                continue;
            }

            $weightTotal += $weight;
            $weightedSum += ((float) $component->score_normalized) * $weight;
        }

        if ($weightTotal <= 0) {
            return null;
        }

        return round($weightedSum / $weightTotal, 2);
    }

    private function normalizeNullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
