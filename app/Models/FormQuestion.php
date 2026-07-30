<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormQuestion extends Model
{
    public const TYPE_SHORT_TEXT = 'short_text';
    public const TYPE_PARAGRAPH = 'paragraph';
    public const TYPE_RADIO = 'radio';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_DROPDOWN = 'dropdown';
    public const TYPE_RATING = 'rating';
    public const TYPE_LINEAR_SCALE = 'linear_scale';
    public const TYPE_IMAGE_UPLOAD = 'image_upload';
    public const TYPE_FILE_UPLOAD = 'file_upload';

    protected $fillable = [
        'form_id',
        'position',
        'question_text',
        'question_image_path',
        'question_type',
        'is_required',
        'settings',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'settings' => 'array',
    ];

    public static function allTypes(): array
    {
        return [
            self::TYPE_SHORT_TEXT,
            self::TYPE_PARAGRAPH,
            self::TYPE_RADIO,
            self::TYPE_CHECKBOX,
            self::TYPE_DROPDOWN,
            self::TYPE_RATING,
            self::TYPE_LINEAR_SCALE,
            self::TYPE_IMAGE_UPLOAD,
            self::TYPE_FILE_UPLOAD,
        ];
    }

    public static function uploadTypes(): array
    {
        return [
            self::TYPE_IMAGE_UPLOAD,
            self::TYPE_FILE_UPLOAD,
        ];
    }

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'form_id');
    }

    public function options()
    {
        return $this->hasMany(FormOption::class, 'question_id')->orderBy('position')->orderBy('id');
    }
}
