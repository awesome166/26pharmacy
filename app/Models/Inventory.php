<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant;


    use \App\AccessControl\Traits\BelongsToTenant;

    protected $primaryKey = 'inventory_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'inventory_id',
        'account_id',
        'drug_id',
        'batch_id',
        'selling_price',
        'quantity_on_hand',
    ];



    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class, 'drug_id', 'drug_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'batch_id', 'batch_id');
    }
}