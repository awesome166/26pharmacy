<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountsPayable extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounting_accounts_payables';

    protected $fillable = [
        'vendor_type',
        'vendor_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'amount',
        'amount_paid',
        'status',
        'description',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function vendor()
    {
        return $this->morphTo();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'payable_id');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->amount - $this->amount_paid;
    }
}
