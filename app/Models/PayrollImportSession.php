<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollImportSession extends Model
{
    protected $fillable = [
        'source_brand', 'source_file_name', 'source_file_type',
        'periode', 'sheet_name', 'total_rows', 'new_count',
        'updated_count', 'unmatched_count', 'skipped_count',
        'status', 'file_path', 'imported_by', 'completed_at', 'error_message',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PayrollImportRow::class, 'session_id');
    }

    public function pendingRows(): HasMany
    {
        return $this->hasMany(PayrollImportRow::class, 'session_id')
                    ->where('row_status', 'update_pending');
    }

    public function unmatchedRows(): HasMany
    {
        return $this->hasMany(PayrollImportRow::class, 'session_id')
                    ->where('row_status', 'unmatched');
    }

    public function needsReview(): bool
    {
        return $this->rows()->whereIn('row_status', ['update_pending', 'unmatched'])->exists();
    }

    public function getBrandLabelAttribute(): string
    {
        return match ($this->source_brand) {
            'ah_pek' => 'Ah Pek Kopitiam',
            'tokio'  => 'Tokio-O!',
            default  => 'Other',
        };
    }
}
