<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLedger extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant;


    protected $primaryKey = 'event_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'event_id',
        'tenant_id',
        'branch_id',
        'device_id',
        'actor_user_id',
        'event_type',
        'event_version',
        'event_payload',
        'local_sequence',
        'event_time_utc',
        'event_hash',
        'received_at_cloud',
    ];

    protected $casts = [
        'event_payload' => 'array',
        'event_time_utc' => 'datetime',
        'received_at_cloud' => 'datetime',
    ];


    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'id');
    }
}