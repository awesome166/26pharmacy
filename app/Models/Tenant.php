<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Tenant extends Model
{
    protected $primaryKey = 'tenant_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'legal_name',
        'tax_identifier',
        'regulatory_metadata',
    ];

    protected $casts = [
        'regulatory_metadata' => 'array',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'tenant_id', 'tenant_id');
    }

    public function eventLedger(): HasMany
    {
        return $this->hasMany(EventLedger::class, 'tenant_id', 'tenant_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id');
    }

    // Through relationships
    public function devices(): HasManyThrough
    {
        return $this->hasManyThrough(Device::class, Branch::class, 'tenant_id', 'branch_id', 'tenant_id', 'branch_id');
    }

    public function sales(): HasManyThrough
    {
        return $this->hasManyThrough(Sale::class, Branch::class, 'tenant_id', 'branch_id', 'tenant_id', 'branch_id');
    }

    public function inventories(): HasManyThrough
    {
        return $this->hasManyThrough(Inventory::class, Branch::class, 'tenant_id', 'branch_id', 'tenant_id', 'branch_id');
    }

    public function financialDaySummaries(): HasManyThrough
    {
        return $this->hasManyThrough(FinancialDaySummary::class, Branch::class, 'tenant_id', 'branch_id', 'tenant_id', 'branch_id');
    }
}