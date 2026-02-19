<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User; // Assuming User model is in App\Models

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounting_journal_entries';

    protected $fillable = [
        'entry_number',
        'reference',
        'date',
        'description',
        'status', // draft, posted, voided
        'type',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(JournalEntryDetail::class, 'journal_entry_id');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted()
    {
        return $this->status === 'posted';
    }

    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isVoided()
    {
        return $this->status === 'voided';
    }
}
