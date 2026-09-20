<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\UpdateDosageInstructionsRequest;
use App\Models\SaleItem;
use App\Services\EventLedgerService;
use AbacPermissions\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleItemController extends Controller
{
    public function updateDosageInstructions(string $saleItemId, UpdateDosageInstructionsRequest $request)
    {
        $accountId = app(TenantContext::class)->getAccountId();
        $saleItem = SaleItem::with('sale')
            ->whereHas('sale', fn ($query) => $query->where('account_id', $accountId))
            ->find($saleItemId);

        if (!$saleItem) {
            return response()->json(['message' => 'Sale item not found'], 404);
        }
        $device = app(\App\Services\DeviceContextService::class)->currentDevice((string) $accountId);
        if ((string) $saleItem->sale->branch_id !== (string) $device->branch_id) {
            return response()->json(['message' => 'Sale item is outside the installed device branch'], 403);
        }

        DB::transaction(function () use ($saleItem, $request, $accountId, $device): void {
            $saleItem->dosage_instructions = $request->validated()['dosage_instructions'];
            $saleItem->save();
            app(EventLedgerService::class)->emitEvent([
                'account_id' => $accountId, 'device_id' => $device->device_id,
                'actor_user_id' => $request->user()?->id,
                'event_type' => 'SALE_ITEM_DOSAGE_AMENDED', 'project_locally' => false,
                'event_payload' => ['sale_id' => $saleItem->sale_id, 'sale_item_id' => $saleItem->id,
                    'dosage_instructions' => $saleItem->dosage_instructions],
            ]);
        });

        return response()->json([
            'message' => 'Dosage instructions updated successfully',
            'data' => $saleItem->fresh(['drug'])
        ]);
    }
}
