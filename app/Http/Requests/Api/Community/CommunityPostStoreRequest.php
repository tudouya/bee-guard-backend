<?php

namespace App\Http\Requests\Api\Community;

use Illuminate\Foundation\Http\FormRequest;

class CommunityPostStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:question,experience'],
            'title' => ['required', 'string', 'min:4', 'max:160'],
            'content' => ['required', 'string', 'min:20', 'max:2000'],
            'images' => ['sometimes', 'array', 'max:3'],
            'images.*' => ['integer', 'min:1'],
            'disease_code' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
        ];
    }
}
