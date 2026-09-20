<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Batch extends Model
{
    use HasUlids, \AbacPermissions\Tenancy\UsesTenant;

    protected $primaryKey = 'id';
    protected $appends = ['batch_number'];

    protected $fillable = [
        'id',
        'account_id',
        'branch_id',
        'drug_id',
        'manufacture_date',
        'manufacturer',
        'supplier',
        'received_date',
        'is_active',
        'expiry_date',
        'lot_number',
        'quantity',
        'quantity_received',
        'cost_price',
        'name',
        'storage_location',
    ];

    protected $casts = [
        'manufacture_date' => 'date',
        'received_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'cost_price' => 'decimal:2',
    ];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class, 'drug_id', 'id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'batch_id', 'id');
    }

    public function getBatchNumberAttribute(): ?string
    {
        return $this->lot_number;
    }

    public function getQuantityReceivedAttribute(): ?int
    {
        return $this->attributes['quantity_received'] ?? $this->attributes['quantity_recieved'] ?? 0;
    }
}
