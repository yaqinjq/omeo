<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeImportSession extends Model
{
    protected $fillable = [
        'source_file_name', 'source_file_path',
        'total_rows', 'new_count', 'updated_count', 'skipped_count', 'account_created_count',
        'status', 'imported_by', 'error_message', 'parsed_data',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function getParsedRowsAttribute(): array
    {
        if (empty($this->parsed_data)) {
            return [];
        }
        return json_decode($this->parsed_data, true) ?? [];
    }
}
