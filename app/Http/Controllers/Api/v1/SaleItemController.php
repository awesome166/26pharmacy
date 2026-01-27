<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\UpdateDosageInstructionsRequest;
use App\Models\SaleItem;
use Illuminate\Http\Request;

class SaleItemController extends Controller
{
    public function updateDosageInstructions(string $saleItemId, UpdateDosageInstructionsRequest $request)
    {
        $saleItem = SaleItem::with('sale')->find($saleItemId);

        if (!$saleItem) {
            return response()->json(['message' => 'Sale item not found'], 404);
        }

        // Update dosage instructions
        $saleItem->dosage_instructions = $request->validated()['dosage_instructions'];
        $saleItem->save();

        return response()->json([
            'message' => 'Dosage instructions updated successfully',
            'data' => $saleItem->fresh(['drug'])
        ]);
    }
}
