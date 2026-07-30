<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppraisalPeriod extends Model
{
    protected $table = 'appraisal_periods';
    protected $fillable = ['name','start_date','end_date','is_active','type'];
    protected $casts = ['start_date'=>'date','end_date'=>'date','is_active'=>'boolean'];
}
