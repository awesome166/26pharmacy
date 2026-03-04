<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'id',
        'journal_entry_id',
        'chart_of_account_id',
        'debit',
        'credit',
        'reference_type',
        'reference_id',
        'memo',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(\App\Models\JournalEntry::class, 'journal_entry_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ChartOfAccount::class, 'chart_of_account_id');
    }
}
