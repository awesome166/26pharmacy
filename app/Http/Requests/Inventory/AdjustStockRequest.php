<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_id' => 'required|ulid|exists:inventory,id',
            'quantity_change' => 'nullable|integer',
            'reason' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'drug_id' => 'nullable|ulid|exists:drugs,id',
            'selling_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'remove_from_inventory' => 'nullable|boolean',
        ];
    }
}
