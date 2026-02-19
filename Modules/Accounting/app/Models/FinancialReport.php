<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class FinancialReport extends Model
{
    use HasFactory;

    protected $table = 'accounting_financial_reports';

    protected $fillable = [
        'name',
        'type', // balance_sheet, income_statement
        'parameters',
        'data',
        'generated_by',
    ];

    protected $casts = [
        'parameters' => 'array',
        'data' => 'array',
    ];

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
