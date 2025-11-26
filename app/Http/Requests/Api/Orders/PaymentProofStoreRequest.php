<?php

namespace App\Http\Requests\Api\Orders;

use Illuminate\Foundation\Http\FormRequest;

class PaymentProofStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'method' => ['required','string','max:32'],
            'trade_no' => ['nullable','string','min:6','max:128'],
            'amount' => ['required','numeric','min:0.01'],
            'remark' => ['nullable','string','max:500'],
            'images' => ['required','array','min:1','max:3'],
            'images.*' => ['required'], // 可为上传ID或URL
        ];
    }
}
