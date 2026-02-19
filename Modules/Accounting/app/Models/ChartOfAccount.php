<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounting_chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'type', // Asset, Liability, Equity, Revenue, Expense
        'category',
        'parent_id',
        'is_group',
        'is_bank',
        'opening_balance',
        'current_balance',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_group' => 'boolean',
        'is_bank' => 'boolean',
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function journalDetails()
    {
        return $this->hasMany(JournalEntryDetail::class, 'chart_of_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
