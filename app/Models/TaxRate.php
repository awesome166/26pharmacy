<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class TaxRate extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'tenant_id',
        'jurisdiction',
        'tax_name',
        'percentage',
        'tax_type',
        'applicable_categories',
        'description',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'applicable_categories' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];
}