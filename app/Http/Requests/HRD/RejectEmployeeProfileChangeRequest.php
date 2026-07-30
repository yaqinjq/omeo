<?php

declare(strict_types=1);

namespace App\Http\Requests\HRD;

use Illuminate\Foundation\Http\FormRequest;

class RejectEmployeeProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'review_note.required' => 'Alasan penolakan wajib diisi.',
            'review_note.max' => 'Alasan penolakan maksimal 2000 karakter.',
        ];
    }
}
