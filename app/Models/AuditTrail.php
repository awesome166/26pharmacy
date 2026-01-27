<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class AuditTrail extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $table = 'audit_trail';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'account_id',
        'entity_type',
        'entity_id',
        'action',
        'actor_user_id',
        'old_values',
        'new_values',
        'timestamp',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'timestamp' => 'datetime',
    ];

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\AbacPermissions\Models\Account::class, 'account_id', 'id');
    }
}