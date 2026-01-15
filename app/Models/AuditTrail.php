<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditTrail extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant;

    protected $primaryKey = 'audit_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'audit_id',
        'entity_type',
        'entity_id',
        'action',
        'actor_user_id',
        'timestamp',
        'metadata',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'user_id');
    }
}