<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalIndicator extends Model
{
    protected $table = 'appraisal_indicators';
    protected $fillable = ['category','question','description','weight','max_score','scale_labels','template_id','sort_order'];
    protected $casts = ['scale_labels' => 'array'];

    public function template()
    {
        return $this->belongsTo(AppraisalCriteriaTemplate::class, 'template_id');
    }
}
