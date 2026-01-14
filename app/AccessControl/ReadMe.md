# Access Control for Laravel

A role-based access control package for Laravel with middleware for route protection and Blade directives for permission checks.

## Installation

1. **Add Resource Routes**

  In `routes/web.php`:
  ```php
  Route::resource('permissions', PermissionController::class);
  ```

  In `routes/api.php`:
  ```php
  Route::resource('permissions', PermissionController::class);
  ```

2. **Publish Migrations**

  Publish and run the package migrations to create the necessary tables.

## Configuration

1. **Create and Register the AuthServiceProvider**

  In `app/Providers/AuthServiceProvider.php`:
  ```php
  namespace App\Providers;

  use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
  use Illuminate\Support\Facades\Gate;
  use App\Models\User;

  class AuthServiceProvider extends ServiceProvider
  {
     public function boot(): void
     {
        Gate::before(function (User $user, string $ability) {
          return $user->hasPermissionTo($ability) ? true : null;
        });
     }
  }
  ```

2. **Add Providers to `App\Providers`**

  ```php
  return [
     App\Providers\AppServiceProvider::class, // existing
     App\Providers\AuthServiceProvider::class, // new
  ];
  ```

## Usage

- **Register Middleware in `app/Http/Kernel.php`:**
  ```php
  'permission' => \App\AccessControl\Middleware\EnsurePermission::class,
  ```

- **Protect Routes:**
  ```php
  Route::get('/students', 'StudentController@index')->middleware('permission:view-student');
  ```

- **Gate Authorization in Controllers:**
  ```php
  if (Gate::denies('edit-student')) {
    abort(403);
  }
  ```

- **Blade Directive Example:**
  ```blade
  @can('edit-student')
    <!-- Content for users with edit-student permission -->
  @endcan
  ```

## User Model Enhancements

- **Add Relationships and Permission Methods:**

  ```php
  use App\AccessControl\Traits\BelongsToTenant;
  use App\AccessControl\Models\Permission;
  use App\AccessControl\Models\Role;
  use Illuminate\Support\Facades\Cache;

  class User extends Authenticatable
  {

        use  BelongsToTenant;
      // Relationships
      public function roles()
      {
          return $this->belongsToMany(Role::class, 'user_roles');
      }

      public function permissions()
      {
          return $this->belongsToMany(Permission::class, 'user_permissions');
      }

      // Cached permissions
      public function getCachedPermissionsAttribute()
      {
          return Cache::rememberForever("permissions_user_{$this->id}", function () {
              return $this->allPermissions()->pluck('name')->unique();
          });
      }

      public function allPermissions()
      {
          return $this->permissions
              ->merge($this->roles->flatMap->permissions)
              ->unique('id');
      }

      public function hasPermissionTo($permission)
      {
          return $this->getCachedPermissionsAttribute()->contains($permission);
      }
  }
  ```
