<?php

namespace App\Http\Requests;

use App\Models\AssessmentForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreAssessmentFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role, ['admin', 'hrd'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(AssessmentForm::allTypes())],
            'department_id' => Schema::hasTable('departments')
                ? ['nullable', 'integer', 'exists:departments,id']
                : ['nullable'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
