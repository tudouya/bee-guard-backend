<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nickname = $this->input('nickname');
        if (is_string($nickname)) {
            $this->merge(['nickname' => trim($nickname)]);
        }
    }

    public function rules(): array
    {
        return [
            'nickname' => ['sometimes','nullable','string','max:20'],
            // Accept relative storage path or our absolute storage URL; reject external in controller.
            'avatar' => ['sometimes','nullable','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.max' => '昵称长度不能超过 20 个字符',
        ];
    }
}
