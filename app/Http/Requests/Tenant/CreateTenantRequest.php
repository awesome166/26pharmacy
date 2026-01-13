<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Simplified for initial setup
    }

    public function rules(): array
    {
        return [
            'legal_name' => 'required|string|max:255',
            'tax_identifier' => 'required|string|unique:tenants,tax_identifier',
            'regulatory_metadata' => 'nullable|array',
        ];
    }
}
