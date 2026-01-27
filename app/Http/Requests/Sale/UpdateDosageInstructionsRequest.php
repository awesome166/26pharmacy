<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDosageInstructionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dosage_instructions' => 'required|array',
            'dosage_instructions.frequency' => 'nullable|string',
            'dosage_instructions.full_frequency' => 'nullable|string',
            'dosage_instructions.route' => 'nullable|string',
            'dosage_instructions.measurement' => 'nullable|string',
            'dosage_instructions.special' => 'nullable|array',
            'dosage_instructions.special.*' => 'string',
            'dosage_instructions.duration' => 'nullable|string',
        ];
    }
}
