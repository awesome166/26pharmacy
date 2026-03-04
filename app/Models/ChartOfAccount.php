<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use \AbacPermissions\Tenancy\UsesTenant, HasUlids;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'id',
        'account_id',
        'code',
        'name',
        'type',
        'parent_id',
        'description',
        'is_group',
        'is_active',
    ];

    protected $casts = [
        'is_group' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(\App\Models\JournalEntryLine::class, 'chart_of_account_id');
    }
}
