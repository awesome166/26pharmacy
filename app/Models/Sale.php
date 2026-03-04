<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Sale extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'account_id',
        'user_id',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'payment_type',
        'payment_metadata',
        'cash_received',
        'change_amount',
        'finalized_at',
        'total_returned_amount',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'payment_metadata' => 'array',
        'finalized_at' => 'datetime',
        'total_returned_amount' => 'decimal:2',
    ];

    protected $appends = [
        'customer',
        'customer_name',
        'customer_dob',
        'customer_phone',
        'customer_email',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Customer::class, 'customer_sales', 'sale_id', 'customer_id')
            ->withTimestamps();
    }

    public function returns()
    {
        return $this->hasMany(\App\Models\SalesReturn::class, 'sale_id', 'id');
    }

    protected function resolvePrimaryCustomer(): ?\App\Models\Customer
    {
        if ($this->relationLoaded('customers')) {
            return $this->customers->first();
        }

        return $this->customers()->first();
    }

    public function getCustomerAttribute(): ?\App\Models\Customer
    {
        return $this->resolvePrimaryCustomer();
    }

    public function getCustomerNameAttribute(): ?string
    {
        return $this->resolvePrimaryCustomer()?->name;
    }

    public function getCustomerDobAttribute(): ?string
    {
        $dob = $this->resolvePrimaryCustomer()?->dob;
        return $dob ? $dob->format('Y-m-d') : null;
    }

    public function getCustomerPhoneAttribute(): ?string
    {
        return $this->resolvePrimaryCustomer()?->phone;
    }

    public function getCustomerEmailAttribute(): ?string
    {
        return $this->resolvePrimaryCustomer()?->email;
    }
}
