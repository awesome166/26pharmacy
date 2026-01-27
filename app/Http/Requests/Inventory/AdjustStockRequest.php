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
            'branch_id' => 'required|string|exists:branches,branch_id',
            'batch_id' => 'required|ulid|exists:batches,batch_id',
            'quantity_change' => 'required|integer',
            'reason' => 'required|string|max:255',
        ];
    }
}
