<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Inventory extends Model
{
    protected $table = 'inventory';
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'account_id',
        'drug_id',
        'batch_id',
        'selling_price',
        'cost_price',
        'reorder_level',
        'location',
        'is_active',
        'quantity_on_hand',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class, 'drug_id', 'id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'id');
    }
}