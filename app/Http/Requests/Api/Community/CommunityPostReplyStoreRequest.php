<?php

namespace App\Http\Requests\Api\Community;

use Illuminate\Foundation\Http\FormRequest;

class CommunityPostReplyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:5', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
