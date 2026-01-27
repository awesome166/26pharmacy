<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class User extends Authenticatable
{
     use \AbacPermissions\Traits\HasAbac {
        permissions as traitPermissions;
     }



    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasUlids;

    protected $primaryKey = 'id';
    // public $incrementing = false;
    // protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // 'id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $with = ['accounts', 'roles'];

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
     * Override permissions to support eager loading.
     */
    public function permissions(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(
            \AbacPermissions\Models\Permission::class,
            'assignee',
            'assigned_permissions',
            'assignee_id',
            'permission_id'
        );
    }


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

    // // AccessControl Roles & Permissions
    // public function roles()
    // {
    //     return $this->belongsToMany(\App\AccessControl\Models\Role::class, 'user_roles', 'user_id', 'role_id');
    // }

    // public function permissions()
    // {
    //     return $this->belongsToMany(\App\AccessControl\Models\Permission::class, 'user_permissions', 'user_id', 'permission_id');
    // }

    // public function getAllPermissionsAttribute()
    // {
    //     return $this->getCachedPermissionsAttribute();
    // }

    // public function getCachedPermissionsAttribute()
    // {
    //     return \Illuminate\Support\Facades\Cache::rememberForever("permissions_user_{$this->id}", function () {
    //         return $this->allPermissions();
    //     });
    // }

    // public function allPermissions()
    // {
    //     $direct = $this->permissions->pluck('name');
    //     $fromRoles = $this->roles->flatMap->permissions->pluck('name');

    //     return $direct->merge($fromRoles)->unique()->values();
    // }

    // public function hasPermissionTo($permission)
    // {
    //     return $this->all_permissions->contains($permission);
    // }
}
