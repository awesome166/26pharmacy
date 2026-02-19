<?php

use App\Models\Role;
use AbacPermissions\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

try {
    DB::beginTransaction();

    echo "Creating test role...\n";
    $role = Role::create([
        'name' => 'Test Role ' . Str::random(5),
        'description' => 'Test Description',
        'zeus_level' => 'none'
    ]);

    echo "Creating test permission...\n";
    $permission = Permission::create([
        'name' => 'test.permission.' . Str::random(5),
        'type' => 'on-off',
        'description' => 'Test Permission'
    ]);

    echo "Attaching permission to role...\n";
    // This should fail if the pivot model isn't correctly configured to generate an ID
    $role->permissions()->attach($permission->id, ['access' => ['on']]);

    echo "Success! Permission attached.\n";

    // Cleanup
    DB::rollBack();
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error caught: " . $e->getMessage() . "\n";
    exit(1);
}
