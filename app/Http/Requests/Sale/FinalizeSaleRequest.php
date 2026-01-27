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
            'account_id' => 'required|exists:accounts,id',
            'user_id' => 'required|exists:users,id',
            'tax_amount' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'payment_type' => 'required|string|in:cash,card,momo',
            'cash_received' => 'nullable|numeric|min:0|required_if:payment_type,cash',
            'change_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.batch_id' => 'required|ulid|exists:batches,id',
            'items.*.drug_id' => 'required|ulid|exists:drugs,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.inventory_id' => 'required|ulid|exists:inventory,id',
            'items.*.price' => 'required|numeric|min:0',
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