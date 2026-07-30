<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AssessmentForm extends Model
{
    public const TYPE_IQ = 'iq';
    public const TYPE_DISC = 'disc';
    public const TYPE_TIU = 'tiu';
    public const TYPE_DIFERENSIAL = 'diferensial';
    public const TYPE_FAT = 'fat';
    public const TYPE_CUSTOM = 'custom';

    /**
     * @var array<string,bool>
     */
    private static array $columnSupportCache = [];

    protected $table = 'forms';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'department_id',
        'duration_minutes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $form): void {
            if (! $form->code) {
                $form->code = 'FORM-' . Str::upper(Str::random(8));
            }
        });
    }

    /**
     * @return array<string,string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_IQ => 'IQ',
            self::TYPE_DISC => 'DISC',
            self::TYPE_TIU => 'TIU',
            self::TYPE_DIFERENSIAL => 'Diferensial',
            self::TYPE_FAT => 'FAT',
            self::TYPE_CUSTOM => 'Custom',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function allTypes(): array
    {
        return array_keys(self::typeLabels());
    }

    /**
     * @return array<string,string>
     */
    public static function builderTypes(): array
    {
        return self::typeLabels();
    }

    /**
     * @return array<string,string>
     */
    public static function importableChoiceTypes(): array
    {
        return [
            self::TYPE_IQ => self::typeLabels()[self::TYPE_IQ],
            self::TYPE_TIU => self::typeLabels()[self::TYPE_TIU],
            self::TYPE_DIFERENSIAL => self::typeLabels()[self::TYPE_DIFERENSIAL],
            self::TYPE_FAT => self::typeLabels()[self::TYPE_FAT],
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function assignableTypes(): array
    {
        return [
            self::TYPE_IQ => 'Test IQ',
            self::TYPE_DISC => 'Test DISC',
            self::TYPE_TIU => 'Test TIU',
            self::TYPE_DIFERENSIAL => 'Test Diferensial',
            self::TYPE_FAT => 'Test FAT',
            self::TYPE_CUSTOM => 'Test Custom',
        ];
    }

    /**
     * @return array<int,string>
     */
    public static function objectiveScoreTypes(): array
    {
        return [
            self::TYPE_IQ,
            self::TYPE_TIU,
            self::TYPE_DIFERENSIAL,
            self::TYPE_FAT,
        ];
    }

    public static function labelFor(string $type): string
    {
        return self::typeLabels()[$type] ?? strtoupper($type);
    }

    public static function isDiscType(string $type): bool
    {
        return $type === self::TYPE_DISC;
    }

    public static function isObjectiveScoreType(string $type): bool
    {
        return in_array($type, self::objectiveScoreTypes(), true);
    }

    public static function supportsDepartmentAudienceColumn(): bool
    {
        $cacheKey = static::class . '|department_id';

        return self::$columnSupportCache[$cacheKey] ??= Schema::hasColumn((new static())->getTable(), 'department_id');
    }

    public function questions()
    {
        return $this->hasMany(FormQuestion::class, 'form_id')->orderBy('position')->orderBy('id');
    }

    public function assignments()
    {
        return $this->hasMany(FormAssignment::class, 'form_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function audienceDepartmentKey(): string
    {
        if (! self::supportsDepartmentAudienceColumn() || ! $this->department_id) {
            return 'general';
        }

        return 'department:' . (int) $this->department_id;
    }

    public function audienceDepartmentName(): string
    {
        if (! self::supportsDepartmentAudienceColumn()) {
            return 'Umum / Semua Departemen';
        }

        return trim((string) ($this->department?->name ?? '')) !== ''
            ? (string) $this->department->name
            : 'Umum / Semua Departemen';
    }
}
