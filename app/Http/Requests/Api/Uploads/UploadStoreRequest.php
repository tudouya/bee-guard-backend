<?php

namespace App\Http\Requests\Api\Uploads;

use Illuminate\Foundation\Http\FormRequest;

class UploadStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file' => ['required','file','mimes:jpg,jpeg,png,webp','max:3072'], // KB
        ];
    }
}

