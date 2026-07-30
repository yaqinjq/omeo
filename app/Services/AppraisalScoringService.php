<?php

namespace App\Services;

use App\Models\Appraisal;

class AppraisalScoringService
{
    /**
     * Hitung skor final berbasis weight pada appraisal_indicators.
     * score diasumsikan skala 1..5 (atau 1..10), weight float (mis: 0.2).
     */
    public function calculateFinalScore(Appraisal $appraisal): float
    {
        $details = $appraisal->details()->with('indicator')->get();

        $totalWeight = 0.0;
        $weighted = 0.0;

        foreach ($details as $d) {
            $w = (float) ($d->indicator->weight ?? 0);
            $totalWeight += $w;
            $weighted += ((float) $d->score) * $w;
        }

        if ($totalWeight <= 0) {
            // fallback: rata-rata sederhana
            $avg = $details->avg('score') ?? 0;
            return (float) $avg;
        }

        return $weighted / $totalWeight;
    }
}
