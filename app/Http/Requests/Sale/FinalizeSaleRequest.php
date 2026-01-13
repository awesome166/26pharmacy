<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|uuid|exists:tenants,tenant_id',
            'branch_id' => 'required|uuid|exists:branches,branch_id',
            'device_id' => 'required|uuid|exists:devices,device_id',
            'user_id' => 'required|uuid|exists:users,user_id',
            'subtotal' => 'required|numeric|min:0',
            'jurisdiction' => 'required|string',
            'payment_type' => 'required|string|in:cash,card,mobile_money',
            'items' => 'required|array|min:1',
            'items.*.batch_id' => 'required|uuid|exists:batches,batch_id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ];
    }
}
