<?php

namespace App\Support;

class AppraisalGrading
{
    /**
     * 6-tier classification bands on the 1-5 star scale, ordered highest first.
     * `min` is inclusive. Mirrors the IPK-style rubric requested by owner/HRD.
     */
    public const BANDS = [
        ['min' => 4.51, 'label' => 'Outstanding',        'label_id' => 'Sangat Istimewa',   'color' => '#0284C7', 'bg' => '#E0F2FE'],
        ['min' => 4.01, 'label' => 'Exceed Expectation', 'label_id' => 'Melebihi Ekspektasi','color' => '#16A34A', 'bg' => '#DCFCE7'],
        ['min' => 3.51, 'label' => 'Above Expectation',  'label_id' => 'Di Atas Ekspektasi', 'color' => '#65A30D', 'bg' => '#F0FDF4'],
        ['min' => 3.00, 'label' => 'Meet Expectation',   'label_id' => 'Sesuai Ekspektasi',  'color' => '#D97706', 'bg' => '#FEF9C3'],
        ['min' => 2.50, 'label' => 'Need Improvement',   'label_id' => 'Perlu Perbaikan',    'color' => '#EA580C', 'bg' => '#FFEDD5'],
        ['min' => 0.00, 'label' => 'Unsatisfactory',     'label_id' => 'Tidak Memuaskan',    'color' => '#DC2626', 'bg' => '#FEE2E2'],
    ];

    /**
     * Classify a score on the 1-5 star scale into its band label.
     */
    public static function classify(float $score): string
    {
        foreach (self::BANDS as $band) {
            if ($score >= $band['min']) {
                return $band['label'];
            }
        }

        return self::BANDS[array_key_last(self::BANDS)]['label'];
    }

    public static function band(?string $label): ?array
    {
        foreach (self::BANDS as $band) {
            if ($band['label'] === $label) {
                return $band;
            }
        }

        return null;
    }

    public static function color(?string $label): string
    {
        return self::band($label)['color'] ?? '#94A3B8';
    }

    public static function bg(?string $label): string
    {
        return self::band($label)['bg'] ?? '#F1F5F9';
    }

    public static function labelId(?string $label): string
    {
        return self::band($label)['label_id'] ?? ($label ?? '-');
    }
}
