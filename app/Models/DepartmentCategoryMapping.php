<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DepartmentCategoryMapping extends Model
{
    protected $fillable = ['match_type', 'match_value', 'category', 'is_active', 'notes'];
    protected $casts    = ['is_active' => 'boolean'];

    public static function resolveCategory(?string $position, ?string $subDept): string
    {
        $position = trim((string) $position);
        $subDept  = trim((string) $subDept);

        if ($position !== '') {
            $hit = self::where('match_type', 'position')
                ->where('is_active', true)
                ->whereRaw('LOWER(match_value) = ?', [strtolower($position)])
                ->first();
            if ($hit) return $hit->category;
        }

        if ($subDept !== '') {
            $hit = self::where('match_type', 'sub_dept')
                ->where('is_active', true)
                ->whereRaw('LOWER(match_value) = ?', [strtolower($subDept)])
                ->first();
            if ($hit) return $hit->category;
        }

        return 'lainnya';
    }
}
