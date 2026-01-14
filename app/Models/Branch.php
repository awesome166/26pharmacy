<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $primaryKey = 'branch_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'branch_id',
        'tenant_id',
        'branch_name',
        'physical_address',
        'license_number',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'branch_id', 'branch_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'branch_user', 'branch_id', 'user_id');
    }

    public function eventLedger(): HasMany
    {
        return $this->hasMany(EventLedger::class, 'branch_id', 'branch_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'branch_id', 'branch_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'branch_id', 'branch_id');
    }

    public function financialDaySummaries(): HasMany
    {
        return $this->hasMany(FinancialDaySummary::class, 'branch_id', 'branch_id');
    }
}