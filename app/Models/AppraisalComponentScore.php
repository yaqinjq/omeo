<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalComponentScore extends Model
{
    protected $table = 'appraisal_component_scores';

    protected $fillable = [
        'appraisal_id',
        'component_key',
        'component_label',
        'source_type',
        'score_raw',
        'score_normalized',
        'weight',
        'notes',
        'payload',
    ];

    protected $casts = [
        'score_raw' => 'decimal:2',
        'score_normalized' => 'decimal:2',
        'weight' => 'decimal:2',
        'payload' => 'array',
    ];

    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class, 'appraisal_id');
    }
}
