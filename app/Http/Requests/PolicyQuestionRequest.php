<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PolicyQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:1000'],
            'document' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'Please enter a question about the policy.',
            'question.string' => 'The question must be a valid string.',
            'question.max' => 'The question may not be greater than 1000 characters.',
            'document.string' => 'The document name must be a valid string.',
            'document.max' => 'The document name may not be greater than 255 characters.',
        ];
    }
}
