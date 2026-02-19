<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $table = 'accounting_approval_requests';

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'requested_by',
        'approved_by',
        'status',
        'comments',
        'level',
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
