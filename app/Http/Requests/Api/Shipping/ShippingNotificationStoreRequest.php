<?php

namespace App\Http\Requests\Api\Shipping;

use Illuminate\Foundation\Http\FormRequest;

class ShippingNotificationStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $couriers = config('shipping.courier_companies', []);

        return [
            'detection_number' => ['required','string','regex:/^[A-Za-z0-9-]{6,20}$/'],
            'courier_company' => ['required','string','in:'.implode(',', array_map(fn($v)=>str_replace(',', '，', $v), $couriers))],
            'tracking_no' => ['required','string','regex:/^[A-Za-z0-9-]{6,}$/','max:64'],
            'shipped_at' => ['nullable','date_format:Y-m-d'],
            'phone' => ['required','string','regex:/^1[3-9]\d{9}$/'],
        ];
    }
}
