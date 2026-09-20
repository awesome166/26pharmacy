<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customer extends Model
{
    use HasFactory, \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'account_id',
        'name',
        'dob',
        'phone',
        'email',
    ];

    protected $casts = [
        'dob' => 'date',
    ];

    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Sale::class, 'customer_sales', 'customer_id', 'sale_id')
            ->withTimestamps();
    }
}
