<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role, ['admin', 'hrd'], true);
    }

    public function rules(): array
    {
        return [
            'iq_score' => ['nullable', 'integer', 'min:0', 'max:300'],
            'disc_d' => ['nullable', 'integer', 'min:0', 'max:999'],
            'disc_i' => ['nullable', 'integer', 'min:0', 'max:999'],
            'disc_s' => ['nullable', 'integer', 'min:0', 'max:999'],
            'disc_c' => ['nullable', 'integer', 'min:0', 'max:999'],
            'interview_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'interview_notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['in_process', 'passed', 'reserve', 'rejected', 'blocked'])],
        ];
    }
}
