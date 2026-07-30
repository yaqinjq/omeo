<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    public const TYPE_DAILY_WORKER = 'daily_worker';

    protected $fillable = [
        'name',
        'type',
        'is_active',
        'is_builder_mode',
        'logo_path',
        'letterhead_html',
        'document_title',
        'opening_paragraph',
        'main_content',
        'closing_paragraph',
        'signatories_json',
        'numbering_prefix',
        'numbering_format',
        'next_sequence',
        'body_html',
        'placeholders_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'placeholders_json' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function dailyWorkerContracts()
    {
        return $this->hasMany(DailyWorkerContract::class);
    }
}
