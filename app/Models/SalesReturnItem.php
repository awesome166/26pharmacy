<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class SalesReturnItem extends Model
{
    use HasUlids;

    protected $table = 'return_items';

    protected $fillable = [
        'id',
        'return_id',
        'sale_item_id',
        'quantity',
        'refund_amount',
        'is_restocked',
        'condition',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'is_restocked' => 'boolean',
    ];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'return_id', 'id');
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id', 'id');
    }
}
