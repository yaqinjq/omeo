<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAnnualImportSession extends Model
{
    protected $fillable = [
        'source_file_name', 'source_brand', 'tahun', 'sheet_name',
        'total_rows', 'new_count', 'updated_count',
        'status', 'imported_by', 'error_message',
    ];

    public function summaries(): HasMany
    {
        return $this->hasMany(PayrollAnnualSummary::class, 'import_session_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
