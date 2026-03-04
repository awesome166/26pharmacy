<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $appends = ['details', 'posted_by'];

    protected $fillable = [
        'id',
        'account_id',
        'entry_number',
        'date',
        'status',
        'description',
        'reference',
        'total_amount',
        'posted_at',
        'posted_by_user_id',
        'voided_at',
        'voided_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(\App\Models\JournalEntryLine::class, 'journal_entry_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'posted_by_user_id');
    }

    public function getDetailsAttribute()
    {
        if ($this->relationLoaded('lines')) {
            return $this->lines;
        }

        return $this->lines()->with('chartOfAccount')->get();
    }

    public function getPostedByAttribute(): ?string
    {
        if ($this->relationLoaded('postedBy')) {
            $postedBy = $this->getRelation('postedBy');
            return $postedBy?->name;
        }

        if (!$this->posted_by_user_id) {
            return null;
        }

        return $this->postedBy()->value('name');
    }
}
