<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasUlids, \AbacPermissions\Tenancy\UsesTenant;

    protected $primaryKey = 'branch_id';

    protected $fillable = ['branch_id', 'account_id', 'name', 'code', 'tax_jurisdiction', 'timezone', 'address', 'phone', 'email', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
