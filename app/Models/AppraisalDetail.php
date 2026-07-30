<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalDetail extends Model
{
    protected $table = 'appraisal_details';
    public $timestamps = false;
    protected $fillable = ['appraisal_id','appraisal_indicator_id','score','comment'];

    public function indicator(){ return $this->belongsTo(AppraisalIndicator::class,'appraisal_indicator_id'); }
}
