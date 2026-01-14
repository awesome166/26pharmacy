<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $primaryKey = 'batch_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'batch_id',
        'drug_id',
        'expiry_date',
        'lot_number',
        'quantity',
        'cost_price',
        'name',
        'manufacturer',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class, 'drug_id', 'drug_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'batch_id', 'batch_id');
    }
}