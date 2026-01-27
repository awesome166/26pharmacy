<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Sale extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'account_id',
        'user_id',
        'customer_name',
        'customer_dob',
        'customer_phone',
        'customer_email',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'payment_type',
        'payment_metadata',
        'cash_received',
        'change_amount',
        'finalized_at',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'payment_metadata' => 'array',
        'finalized_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }
}