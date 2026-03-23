<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:5120'],
            'prompt' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Please upload an image to guide the generation.',
            'image.image' => 'The uploaded file must be a valid image.',
            'image.max' => 'The image may not be greater than 5 MB.',
            'prompt.required' => 'Please enter a prompt for the image.',
            'prompt.string' => 'The prompt must be a valid string.',
            'prompt.max' => 'The prompt may not be greater than 500 characters.',
        ];
    }
}
