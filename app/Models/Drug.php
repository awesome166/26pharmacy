<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Drug extends Model
{
    use HasUlids;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'account_id',
        'name',
        'generic_name',
        'strength',
        'form',
        'route',
        'regulatory_code',
        'manufacturer',
        'supplier',
        'is_prescription',
        'is_controlled',
        'is_narcotic',
        'drug_class',
        'storage_conditions',
        'description',
        'side_effects',
        'contraindications',
        'schedule',
        'alternate_names',
        'metadata',
    ];

    protected $casts = [
        'is_prescription' => 'boolean',
        'is_controlled' => 'boolean',
        'is_narcotic' => 'boolean',
        'alternate_names' => 'array',
        'metadata' => 'array',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'drug_id', 'id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'drug_id', 'id');
    }
}