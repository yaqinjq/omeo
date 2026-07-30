<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedDocument extends Model
{
    protected $table = 'generated_documents';
    public $timestamps = false;
    protected $fillable = ['document_number','employee_id','type','file_path','generated_at'];
    protected $casts = ['generated_at' => 'datetime'];
}
