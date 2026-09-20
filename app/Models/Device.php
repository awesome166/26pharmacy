<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Device extends Model
{
    use HasFactory, HasUlids, \AbacPermissions\Tenancy\UsesTenant;

    protected $primaryKey = 'device_id';

    protected $fillable = [
        'device_id',
        'account_id',
        'branch_id',
        'device_name',
        'trust_status',
        'sync_token_hash',
        'device_type',
        'serial_number',
        'mac_address',
        'license_checked_at',
        'last_seen_at',
    ];

    protected $hidden = ['sync_token_hash'];

    protected $casts = [
        'license_checked_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(\AbacPermissions\Models\Account::class, 'account_id');
    }

    public function eventLedger(): HasMany
    {
        return $this->hasMany(EventLedger::class, 'device_id', 'device_id');
    }
}
