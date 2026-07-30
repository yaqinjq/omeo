<?php

namespace App\Http\Requests;

use App\Models\FormQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && in_array($this->user()->role, ['admin', 'hrd'], true);
    }

    public function rules(): array
    {
        return [
            'question_text' => ['required', 'string'],
            'question_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_question_image' => ['nullable', 'boolean'],
            'question_type' => ['required', Rule::in(FormQuestion::allTypes())],
            'is_required' => ['nullable', 'boolean'],
            'settings.min' => ['nullable', 'integer', 'min:0'],
            'settings.max' => ['nullable', 'integer', 'min:1'],
            'settings.min_label' => ['nullable', 'string', 'max:100'],
            'settings.max_label' => ['nullable', 'string', 'max:100'],
            'settings.disc_mode' => ['nullable', Rule::in(['single_axis', 'dual_axis'])],
            'settings.media_title' => ['nullable', 'string', 'max:255'],
            'settings.media_url' => ['nullable', 'string', 'max:2000'],
            'settings.youtube_url' => ['nullable', 'string', 'max:2000'],
            'settings.answer_accept' => ['nullable', 'string', 'max:100'],
            'settings.answer_max_kb' => ['nullable', 'integer', 'min:64', 'max:10240'],
            'options' => ['nullable', 'array'],
            'options.*.id' => ['nullable', 'integer', 'exists:form_options,id'],
            'options.*.option_text' => ['nullable', 'string', 'max:255'],
            'options.*.value' => ['nullable', 'string', 'max:255'],
            'options.*.weight' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'options.*.meta.disc_axis' => ['nullable', Rule::in(['D', 'I', 'S', 'C'])],
            'options.*.meta.disc_axis_most' => ['nullable', Rule::in(['D', 'I', 'S', 'C'])],
            'options.*.meta.disc_axis_least' => ['nullable', Rule::in(['D', 'I', 'S', 'C'])],
            'options.*.meta.is_correct' => ['nullable', 'boolean'],
        ];
    }
}
