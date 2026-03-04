<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class DailyBookClosure extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $fillable = [
        'id',
        'account_id',
        'business_date',
        'status',
        'closed_at',
        'closed_by_user_id',
        'summary',
        'notes',
    ];

    protected $casts = [
        'business_date' => 'date',
        'closed_at' => 'datetime',
        'summary' => 'array',
    ];
}
