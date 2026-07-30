<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role, ['admin', 'hrd'], true);
    }

    public function rules(): array
    {
        return [
            'option_text' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'meta.disc_axis' => ['nullable', Rule::in(['D', 'I', 'S', 'C'])],
            'meta.disc_axis_most' => ['nullable', Rule::in(['D', 'I', 'S', 'C'])],
            'meta.disc_axis_least' => ['nullable', Rule::in(['D', 'I', 'S', 'C'])],
            'meta.is_correct' => ['nullable', 'boolean'],
        ];
    }
}
