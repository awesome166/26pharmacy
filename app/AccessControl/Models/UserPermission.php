<?php

namespace App\AccessControl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserPermission extends Model
{
    protected $table = 'user_permissions';
    public $timestamps = false;
    protected $fillable = ['user_id', 'permission_id'];
}