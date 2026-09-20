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
            // Totals, prices, tenant and actor are accepted for backwards
            // compatibility only; SaleService derives authoritative values.
            'account_id' => 'nullable|ulid',
            'user_id' => 'nullable|ulid',
            'device_id' => 'nullable|ulid',
            'tax_amount' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'payment_type' => 'required|string|in:cash,card,momo',
            'cash_received' => 'nullable|numeric|min:0|required_if:payment_type,cash',
            'change_amount' => 'nullable|numeric|min:0',
            'customer_name' => 'nullable|string|max:255',
            'customer_id' => 'nullable|ulid|exists:customers,id',
            'customer_phone' => 'nullable|string|max:50|required_with:customer_name,customer_email,customer_dob',
            'customer_email' => 'nullable|email|max:255',
            'customer_dob' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.batch_id' => 'nullable|ulid',
            'items.*.drug_id' => 'nullable|ulid',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.inventory_id' => 'required|ulid|exists:inventory,id',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.prescription_metadata' => 'nullable|array',
            'items.*.dosage_instructions' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.quantity.required' => 'The quantity for item number :position is required.',
            'items.*.quantity.min' => 'The quantity for item number :position must be at least :min.',
        ];
    }
}
