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
        'device_id',
        'actor_user_id',
        'event_type',
        'event_payload',
        'local_sequence',
        'event_time_utc',
        'event_hash',
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