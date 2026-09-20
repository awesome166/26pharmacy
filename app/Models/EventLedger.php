<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class EventLedger extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;


    protected $table = 'event_ledger';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'account_id',
        'branch_id',
        'device_id',
        'actor_user_id',
        'event_type',
        'event_category',
        'event_version',
        'event_payload',
        'local_sequence',
        'global_sequence',
        'event_time_utc',
        'event_hash',
        'previous_hash',
        'sync_status',
        'synced_at',
        'received_at_cloud',
        'metadata',
    ];

    protected $casts = [
        'event_payload' => 'array',
        'event_time_utc' => 'datetime',
        'received_at_cloud' => 'datetime',
        'synced_at' => 'datetime',
        'metadata' => 'array',
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
