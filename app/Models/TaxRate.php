<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $primaryKey = 'tax_rate_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tax_rate_id',
        'jurisdiction',
        'percentage',
        'effective_from',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'effective_from' => 'date',
    ];
}