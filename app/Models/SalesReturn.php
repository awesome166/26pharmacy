<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class SalesReturn extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $table = 'returns';

    protected $fillable = [
        'id',
        'sale_id',
        'account_id',
        'user_id',
        'refund_amount',
        'refund_method',
        'reason',
        'returned_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'returned_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class, 'return_id', 'id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
