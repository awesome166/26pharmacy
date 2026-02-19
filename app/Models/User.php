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
        'created_at',
        'updated_at',
        'two_factor_confirmed_at',
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
        )
        ->using(\AbacPermissions\Models\AssignedPermission::class)
        ->withPivot('access');  // expose access so UserResource can read it
    }



    public function assignedPermissions()
    {
        return $this->morphMany(\App\Models\AssignedPermission::class, 'assignee');
    }





    public function getMorphClass()
    {
        return 'user';
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


    /// delete this after plugin update
        public function getAllPermissions(): string
    {
        $roleIds = $this->roles()->pluck('id');
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        // Get all assigned permissions for this user's roles and direct assignments
        $assignments = \AbacPermissions\Models\AssignedPermission::where(function ($query) use ($roleIds, $accountId) {
            $query->where(function ($q) use ($roleIds) {
                $q->where('assignee_type', 'role')
                    ->whereIn('assignee_id', $roleIds);
            })
            ->orWhere(function ($q) use ($accountId) {
                $q->where('assignee_type', 'user')
                    ->where('assignee_id', $this->id);
            });
        })
        ->with('permission')
        ->get();

        // Expand each assignment and collect all permission strings
        $expandedPermissions = $assignments->flatMap(function ($assignment) {
            return $assignment->getExpandedPermissions();
        });

        // Return unique permissions as comma-separated string
        return $expandedPermissions->unique()->sort()->implode(', ');
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
