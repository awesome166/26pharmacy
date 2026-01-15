<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use \App\AccessControl\Traits\BelongsToTenant;
        use \AbacPermissions\Tenancy\UsesTenant;


    protected $primaryKey = 'sale_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'sale_id',
        'account_id',
        'total_amount',
        'tax_amount',
        'payment_type',
        'finalized_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];



    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id', 'sale_id');
    }
}