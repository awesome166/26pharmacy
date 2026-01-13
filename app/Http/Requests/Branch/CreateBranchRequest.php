<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;

class CreateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|uuid|exists:tenants,tenant_id',
            'branch_name' => 'required|string|max:255',
            'physical_address' => 'nullable|string',
            'license_number' => 'nullable|string',
        ];
    }
}
