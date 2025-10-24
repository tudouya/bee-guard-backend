<?php

namespace App\Http\Requests\Api\Epidemic;

use Illuminate\Foundation\Http\FormRequest;

class EpidemicBulletinListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'province_code' => ['sometimes', 'string', 'max:12'],
            'city_code' => ['sometimes', 'string', 'max:12'],
            'district_code' => ['sometimes', 'string', 'max:12'],
            'risk_level' => ['sometimes', 'in:high,medium,low'],
        ];
    }
}
