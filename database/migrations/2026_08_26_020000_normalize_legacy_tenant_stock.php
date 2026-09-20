<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $drugMap = [];

    /** @var array<string, string> */
    private array $batchMap = [];

    /** @var array<string, string> */
    private array $historicalInventoryMap = [];

    public function up(): void
    {
        DB::transaction(function (): void {
            // Legacy demo data used one global catalog and batch pool for every
            // tenant. Give every stocked item a tenant-owned drug and batch.
            DB::table('inventory')->orderBy('id')->each(function (object $inventory): void {
                if (!$inventory->account_id || !$inventory->branch_id) {
                    return;
                }

                $drugId = $this->tenantDrug(
                    (string) $inventory->account_id,
                    (string) $inventory->drug_id,
                );
                $batchId = $this->tenantBatch(
                    (string) $inventory->account_id,
                    (string) $inventory->branch_id,
                    (string) $inventory->batch_id,
                    $drugId,
                );

                DB::table('inventory')->where('id', $inventory->id)->update([
                    'drug_id' => $drugId,
                    'batch_id' => $batchId,
                    'updated_at' => now(),
                ]);
            });

            // Some legacy seeded sales selected inventory from another tenant.
            // Preserve those historical references using inactive, zero-stock
            // tenant-local snapshots; never move or inflate live inventory.
            DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->join('inventory as i', 'i.id', '=', 'si.inventory_id')
                ->select([
                    'si.id as sale_item_id',
                    's.account_id as sale_account_id',
                    's.branch_id as sale_branch_id',
                    'i.id as inventory_id',
                    'i.account_id as inventory_account_id',
                    'i.branch_id as inventory_branch_id',
                    'i.drug_id',
                    'i.batch_id',
                ])
                ->orderBy('si.id')
                ->each(function (object $row): void {
                    if (!$row->sale_account_id || !$row->sale_branch_id) {
                        return;
                    }

                    $sameOwner = (string) $row->sale_account_id === (string) $row->inventory_account_id
                        && (string) $row->sale_branch_id === (string) $row->inventory_branch_id;

                    $inventoryId = (string) $row->inventory_id;
                    $drugId = (string) $row->drug_id;
                    $batchId = (string) $row->batch_id;

                    if (!$sameOwner) {
                        [$inventoryId, $drugId, $batchId] = $this->historicalInventory(
                            (string) $row->sale_account_id,
                            (string) $row->sale_branch_id,
                            (string) $row->inventory_id,
                        );
                    }

                    DB::table('sale_items')->where('id', $row->sale_item_id)->update([
                        'inventory_id' => $inventoryId,
                        'drug_id' => $drugId,
                        'batch_id' => $batchId,
                        'updated_at' => now(),
                    ]);
                });

            // Unowned batches have no inventory provenance and cannot safely be
            // assigned to one tenant. Keep them for audit, but make them inert.
            DB::table('batches')
                ->whereNull('account_id')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('inventory')
                        ->whereColumn('inventory.batch_id', 'batches.id');
                })
                ->update(['is_active' => false, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Ownership normalization is intentionally irreversible. Recombining
        // tenant records would recreate cross-tenant stock references.
    }

    private function tenantDrug(string $accountId, string $sourceDrugId): string
    {
        $key = $accountId.'|'.$sourceDrugId;
        if (isset($this->drugMap[$key])) {
            return $this->drugMap[$key];
        }

        $source = DB::table('drugs')->where('id', $sourceDrugId)->first();
        if (!$source) {
            throw new RuntimeException("Cannot normalize missing drug {$sourceDrugId}.");
        }

        if ((string) ($source->account_id ?? '') === $accountId) {
            return $this->drugMap[$key] = $sourceDrugId;
        }

        $copy = (array) $source;
        $copy['id'] = (string) Str::ulid();
        $copy['account_id'] = $accountId;
        $copy['created_at'] ??= now();
        $copy['updated_at'] = now();
        DB::table('drugs')->insert($copy);

        return $this->drugMap[$key] = $copy['id'];
    }

    private function tenantBatch(
        string $accountId,
        string $branchId,
        string $sourceBatchId,
        string $drugId,
    ): string {
        $key = $accountId.'|'.$branchId.'|'.$sourceBatchId;
        if (isset($this->batchMap[$key])) {
            return $this->batchMap[$key];
        }

        $source = DB::table('batches')->where('id', $sourceBatchId)->first();
        if (!$source) {
            throw new RuntimeException("Cannot normalize missing batch {$sourceBatchId}.");
        }

        if ((string) ($source->account_id ?? '') === $accountId
            && (string) ($source->branch_id ?? '') === $branchId) {
            DB::table('batches')->where('id', $sourceBatchId)->update([
                'drug_id' => $drugId,
                'updated_at' => now(),
            ]);

            return $this->batchMap[$key] = $sourceBatchId;
        }

        $copy = (array) $source;
        $copy['id'] = (string) Str::ulid();
        $copy['account_id'] = $accountId;
        $copy['branch_id'] = $branchId;
        $copy['drug_id'] = $drugId;
        $copy['created_at'] ??= now();
        $copy['updated_at'] = now();
        DB::table('batches')->insert($copy);

        return $this->batchMap[$key] = $copy['id'];
    }

    /**
     * @return array{string, string, string}
     */
    private function historicalInventory(string $accountId, string $branchId, string $sourceInventoryId): array
    {
        $key = $accountId.'|'.$branchId.'|'.$sourceInventoryId;
        if (isset($this->historicalInventoryMap[$key])) {
            $inventory = DB::table('inventory')->where('id', $this->historicalInventoryMap[$key])->first();

            return [$inventory->id, $inventory->drug_id, $inventory->batch_id];
        }

        $source = DB::table('inventory')->where('id', $sourceInventoryId)->first();
        if (!$source) {
            throw new RuntimeException("Cannot normalize missing inventory {$sourceInventoryId}.");
        }

        $drugId = $this->tenantDrug($accountId, (string) $source->drug_id);
        $batchId = $this->tenantBatch($accountId, $branchId, (string) $source->batch_id, $drugId);
        $copy = (array) $source;
        $copy['id'] = (string) Str::ulid();
        $copy['account_id'] = $accountId;
        $copy['branch_id'] = $branchId;
        $copy['drug_id'] = $drugId;
        $copy['batch_id'] = $batchId;
        $copy['quantity_on_hand'] = 0;
        $copy['is_active'] = false;
        $copy['location'] = 'Legacy sale reference '.substr($sourceInventoryId, -12);
        $copy['created_at'] ??= now();
        $copy['updated_at'] = now();
        DB::table('inventory')->insert($copy);

        $this->historicalInventoryMap[$key] = $copy['id'];

        return [$copy['id'], $drugId, $batchId];
    }
};
