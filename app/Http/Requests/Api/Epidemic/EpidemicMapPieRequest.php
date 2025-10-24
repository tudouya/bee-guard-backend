<?php

namespace App\Http\Requests\Api\Epidemic;

use Illuminate\Foundation\Http\FormRequest;

class EpidemicMapPieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->year ? (int) $this->year : null,
            'compare_year' => $this->compare_year ? (int) $this->compare_year : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'province_code' => ['required', 'string', 'max:12'],
            'district_code' => ['required', 'string', 'max:12'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:2100'],
            'compare_year' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
