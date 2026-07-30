<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalWeightConfig extends Model
{
    protected $table = 'appraisal_weight_configs';

    protected $fillable = [
        'scope',
        'appraisal_type',
        'weight_criteria',
        'weight_kpi',
        'weight_training',
        'weight_skill',
        'weight_position',
        'updated_by',
    ];

    protected $casts = [
        'weight_criteria' => 'decimal:2',
        'weight_kpi'      => 'decimal:2',
        'weight_training' => 'decimal:2',
        'weight_skill'    => 'decimal:2',
        'weight_position' => 'decimal:2',
    ];

    /**
     * Load the config for a given period type.
     * Falls back: per_type → global → hardcoded default.
     */
    public static function loadFor(?string $type): self
    {
        if ($type) {
            $cfg = static::where('scope', 'per_type')
                ->where('appraisal_type', $type)
                ->first();
            if ($cfg) {
                return $cfg;
            }
        }

        $cfg = static::where('scope', 'global')->first();
        if ($cfg) {
            return $cfg;
        }

        return new static([
            'weight_criteria' => 50,
            'weight_kpi'      => 30,
            'weight_training' => 20,
            'weight_skill'    => 0,
            'weight_position' => 0,
        ]);
    }

    public function toWeightsArray(): array
    {
        return [
            'criteria' => (float) $this->weight_criteria,
            'kpi'      => (float) $this->weight_kpi,
            'training' => (float) $this->weight_training,
            'skill'    => (float) $this->weight_skill,
            'position' => (float) $this->weight_position,
        ];
    }

    public function totalWeight(): float
    {
        return array_sum($this->toWeightsArray());
    }

    /** Human-readable formula string, e.g. "Kriteria 50% + KPI 30% + Training 20%" */
    public function formulaString(): string
    {
        $labels = [
            'criteria' => 'Kriteria',
            'kpi'      => 'KPI',
            'training' => 'Training',
            'skill'    => 'Skill',
            'position' => 'Posisi',
        ];
        $parts = [];
        foreach ($this->toWeightsArray() as $key => $w) {
            if ($w > 0) {
                $parts[] = $labels[$key] . ' ' . number_format($w, 0) . '%';
            }
        }
        return implode(' + ', $parts);
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
