<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Device extends Model
{
    use HasUlids;

    protected $primaryKey = 'device_id';

    protected $fillable = [
        'device_id',
        'branch_id',
        'device_name',
        'trust_status',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function eventLedger(): HasMany
    {
        return $this->hasMany(EventLedger::class, 'device_id', 'device_id');
    }
}