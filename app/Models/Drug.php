<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Drug extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant;

    protected $primaryKey = 'drug_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'drug_id',
        'name',
        'strength',
        'regulatory_code',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'drug_id', 'drug_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'drug_id', 'drug_id');
    }
}