<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $appends = [
        'all_permissions',
    ];

    protected $with = [
        'tenants',
        'branches',
        'roles',
    ];

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user', 'user_id', 'tenant_id');
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_user', 'user_id', 'branch_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // AccessControl Roles & Permissions
    public function roles()
    {
        return $this->belongsToMany(\App\AccessControl\Models\Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(\App\AccessControl\Models\Permission::class, 'user_permissions', 'user_id', 'permission_id');
    }

    public function getAllPermissionsAttribute()
    {
        return $this->getCachedPermissionsAttribute();
    }

    public function getCachedPermissionsAttribute()
    {
        return \Illuminate\Support\Facades\Cache::rememberForever("permissions_user_{$this->id}", function () {
            return $this->allPermissions();
        });
    }

    public function allPermissions()
    {
        $direct = $this->permissions->pluck('name');
        $fromRoles = $this->roles->flatMap->permissions->pluck('name');

        return $direct->merge($fromRoles)->unique()->values();
    }

    public function hasPermissionTo($permission)
    {
        return $this->all_permissions->contains($permission);
    }
}
