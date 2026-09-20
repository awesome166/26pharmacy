<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class EventRejection extends Model
{
    use HasUlids;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'rejection_reason',
        'reviewed_by',
        'resolved_at',
        'rejection_details',
        'resolution_action',
        'resolved_event_id',
        'correction_data',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'correction_data' => 'array',
    ];

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }
}