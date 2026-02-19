<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class SaleItem extends Model
{
    use HasUlids;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'sale_id',
        'batch_id',
        'inventory_id',
        'drug_id',
        'quantity',
        'price',
        'line_total',
        'tax_amount',
        'requires_prescription',
        'prescription_metadata',
        'dosage_instructions',
        'is_returned',
        'return_quantity',
        'return_date',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'requires_prescription' => 'boolean',
        'prescription_metadata' => 'array',
        'dosage_instructions' => 'array',
        'is_returned' => 'boolean',
        'return_date' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id', 'id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'id');
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class, 'drug_id', 'id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'id');
    }
}
