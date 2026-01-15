<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialDaySummary extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant;


    protected $primaryKey = 'summary_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'summary_id',
        'account_id',
        'day',
        'gross_sales',
        'tax_collected',
    ];

    protected $casts = [
        'day' => 'date',
        'gross_sales' => 'decimal:2',
        'tax_collected' => 'decimal:2',
    ];


}