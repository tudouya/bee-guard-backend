<?php

namespace App\Http\Requests\Api\Orders;

use Illuminate\Foundation\Http\FormRequest;

class OrderStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount' => ['required','numeric','min:0.01'],
            'package_id' => ['nullable','string','max:64'],
            'package_name' => ['nullable','string','max:191'],
        ];
    }
}

