<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class BankStatementImport extends Model
{
    use HasFactory;

    protected $table = 'accounting_bank_statement_imports';

    protected $fillable = [
        'bank_account_id',
        'file_name',
        'file_path',
        'status',
        'matched_count',
        'unmatched_count',
        'imported_by',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
