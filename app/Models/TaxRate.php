<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class TaxRate extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    public function account()
    {
        return $this->belongsTo(\AbacPermissions\Models\Account::class, 'account_id');
    }

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'account_id',
        'jurisdiction',
        'tax_name',
        'percentage',
        'minimum_taxable_amount',
        'maximum_taxable_amount',
        'calculation_order',
        'is_compound',
        'tax_type',
        'applicable_categories',
        'description',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'minimum_taxable_amount' => 'decimal:2',
        'maximum_taxable_amount' => 'decimal:2',
        'calculation_order' => 'integer',
        'is_compound' => 'boolean',
        'applicable_categories' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];




}
