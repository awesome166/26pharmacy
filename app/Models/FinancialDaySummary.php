<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class FinancialDaySummary extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;


    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'account_id',
        'branch_id',
        'day',
        'gross_sales',
        'net_sales',
        'tax_collected',
        'total_discounts',
        'total_cost',
        'gross_profit',
        'total_transactions',
        'prescription_count',
        'otc_count',
        'cash_collected',
        'card_collected',
        'momo_collected',
        'insurance_billed',
        'returns_amount',
        'customer_count',
        'payment_breakdown',
        'category_breakdown',
        'user_breakdown',
        'is_closed',
        'closed_at',
        'closed_by_user_id',
    ];

    protected $casts = [
        'day' => 'date:Y-m-d',
        'gross_sales' => 'decimal:2',
        'net_sales' => 'decimal:2',
        'tax_collected' => 'decimal:2',
        'total_discounts' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'cash_collected' => 'decimal:2',
        'card_collected' => 'decimal:2',
        'momo_collected' => 'decimal:2',
        'insurance_billed' => 'decimal:2',
        'returns_amount' => 'decimal:2',
        'payment_breakdown' => 'array',
        'category_breakdown' => 'array',
        'user_breakdown' => 'array',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];


}
