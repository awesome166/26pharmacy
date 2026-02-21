<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AbacPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tables = config('abacpermissions.tables', [
            'accounts' => 'accounts',
            'roles' => 'roles',
            'permissions' => 'permissions',
            'account_user' => 'account_user',
            'activity_logs' => 'activity_logs',
            'assigned_permissions' => 'assigned_permissions'
        ]);

        // Clear existing data
        if (DB::table($tables['activity_logs'])->exists()) {
            DB::table($tables['activity_logs'])->delete();
        }
        if (DB::table($tables['assigned_permissions'])->exists()) {
            DB::table($tables['assigned_permissions'])->delete();
        }
        if (DB::table($tables['account_user'])->exists()) {
            DB::table($tables['account_user'])->delete();
        }
        if (DB::table($tables['permissions'])->exists()) {
            DB::table($tables['permissions'])->delete();
        }
        if (DB::table($tables['roles'])->exists()) {
            DB::table($tables['roles'])->delete();
        }
        if (DB::table($tables['accounts'])->exists()) {
            DB::table($tables['accounts'])->delete();
        }
        if (DB::table('users')->exists()) {
            DB::table('users')->delete();
        }

        // Create a system user (pharmacy owner/admin)
        $systemAdminId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $systemAdminId,
            'name' => 'Pharmacy System Admin',
            'email' => 'admin@pharmacy.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a pharmacy staff user
        $pharmacistId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $pharmacistId,
            'name' => 'John Pharmacist',
            'email' => 'pharmacist@pharmacy.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a customer service user
        $customerServiceId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $customerServiceId,
            'name' => 'Sarah Customer Service',
            'email' => 'support@pharmacy.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Create Pharmacy Accounts (Tenants)
        $accounts = [
            [
                'name' => 'MediCare Pharmacy',
                'slug' => 'medicare-pharmacy',
                'plan' => 'premium',
                'metadata' => json_encode([
                    'pharmacy_license' => 'PH123456',
                    'address' => '123 Health St, Medical City',
                    'phone' => '+1-555-123-4567',
                    'operating_hours' => '24/7',
                    'delivery_radius' => 50, // miles
                    'prescription_on',
                    'insurance_providers' => ['Aetna', 'BlueCross', 'Medicare']
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'QuickRx Pharmacy',
                'slug' => 'quickrx-pharmacy',
                'plan' => 'basic',
                'metadata' => json_encode([
                    'pharmacy_license' => 'PH789012',
                    'address' => '456 Wellness Ave, Care Town',
                    'phone' => '+1-555-987-6543',
                    'operating_hours' => '8 AM - 10 PM',
                    'delivery_radius' => 25,
                    'prescription_on',
                    'insurance_providers' => ['Cigna', 'UnitedHealth']
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'HealthPlus Pharmacy',
                'slug' => 'healthplus-pharmacy',
                'plan' => 'enterprise',
                'metadata' => json_encode([
                    'pharmacy_license' => 'PH345678',
                    'address' => '789 Recovery Blvd, Health City',
                    'phone' => '+1-555-456-7890',
                    'operating_hours' => '6 AM - 12 AM',
                    'delivery_radius' => 100,
                    'prescription_on',
                    'insurance_providers' => ['All major providers'],
                    'specialties' => ['Compounding', 'Vaccines', 'Diabetic Supplies']
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $accountIds = [];
        foreach ($accounts as $account) {
            $id = (string) Str::ulid();
            $account['id'] = $id;
            DB::table($tables['accounts'])->insert($account);
            $accountIds[] = $id;
        }

        // 2. Create Roles
        $roles = [
            // System-wide roles (no account_id)
            [
                'account_id' => null,
                'name' => 'Super Admin',
                'zeus_level' => 'system',
                'description' => 'Full system access across all pharmacies',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => null,
                'name' => 'Platform Support',
                'zeus_level' => 'none',
                'description' => 'Platform technical support team',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Pharmacy-specific roles
            [
                'account_id' => $accountIds[0], // MediCare Pharmacy
                'name' => 'Pharmacy Manager',
                'zeus_level' => 'tenant',
                'description' => 'Manage all operations for MediCare Pharmacy',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[0],
                'name' => 'Licensed Pharmacist',
                'zeus_level' => 'none',
                'description' => 'Dispense medications and provide consultations',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[0],
                'name' => 'Pharmacy Technician',
                'zeus_level' => 'none',
                'description' => 'Assist pharmacists and manage inventory',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[0],
                'name' => 'Customer Service',
                'zeus_level' => 'none',
                'description' => 'Handle customer inquiries and orders',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[1], // QuickRx Pharmacy
                'name' => 'Pharmacy Manager',
                'zeus_level' => 'tenant',
                'description' => 'Manage all operations for QuickRx Pharmacy',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[1],
                'name' => 'Pharmacist',
                'zeus_level' => 'none',
                'description' => 'Dispense medications at QuickRx',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $roleIds = [];
        foreach ($roles as $role) {
            // Need to handle referencing account_id which might be in the array or null
            // The logic below assumes $assignedPermissions etc use these IDs.
            // The original code used insertGetId, which returned the ID.

            $id = (string) Str::ulid();
            $role['id'] = $id;
            DB::table($tables['roles'])->insert($role);
            $roleIds[] = $id;
        }

        // 3. Create Permissions (E-commerce Pharmacy Specific)
        $permissions = [
            // Dashboard
            [
                'name' => 'dashboard.view',
                'type' => 'on-off',
                'description' => 'View main dashboard and aggregate statistics',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Accounts (Pharmacies)
            [
                'name' => 'accounts.manage',
                'type' => 'crud',
                'description' => 'Manage pharmacy branches',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Users & Roles
            [
                'name' => 'users.manage',
                'type' => 'crud',
                'description' => 'Manage staff members',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'roles.manage',
                'type' => 'crud',
                'description' => 'Manage roles and permissions limits',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Drugs & Products
            [
                'name' => 'drugs.manage',
                'type' => 'crud',
                'description' => 'Manage drug catalog',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'batches.manage',
                'type' => 'crud',
                'description' => 'Manage product batches',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Inventory
            [
                'name' => 'inventory.manage',
                'type' => 'crud',
                'description' => 'Manage inventory levels',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'inventory.adjust',
                'type' => 'on-off',
                'description' => 'Adjust stock levels manually',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'inventory.transfer',
                'type' => 'on-off',
                'description' => 'Transfer stock between branches',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'inventory.expired',
                'type' => 'on-off',
                'description' => 'Manage and process expired inventory',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Point of Sale (Sales)
            [
                'name' => 'pos.access',
                'type' => 'on-off',
                'description' => 'Access the main Store/POS interface',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'sales.process',
                'type' => 'crud',
                'description' => 'Process and view sales',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'sales.reverse',
                'type' => 'on-off',
                'description' => 'Reverse or void a finalized sale',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Returns
            [
                'name' => 'returns.manage',
                'type' => 'crud',
                'description' => 'Manage sales returns',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'returns.restock',
                'type' => 'on-off',
                'description' => 'Restock returned items to inventory',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Reports & Audit
            [
                'name' => 'reports.view',
                'type' => 'on-off',
                'description' => 'View reporting module',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'reports.export',
                'type' => 'on-off',
                'description' => 'Export ledgers and reports',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'audit_trail.view',
                'type' => 'on-off',
                'description' => 'View the system audit trail',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Settings & Configuration
            [
                'name' => 'settings.manage',
                'type' => 'crud',
                'description' => 'Manage system configurations',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'taxes.manage',
                'type' => 'crud',
                'description' => 'Manage tax rates',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'devices.manage',
                'type' => 'crud',
                'description' => 'Manage registered branch devices',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Sync Operations
            [
                'name' => 'sync.manage',
                'type' => 'on-off',
                'description' => 'Manually trigger data syncs',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $id = (string) Str::ulid();
            $permission['id'] = $id;
            DB::table($tables['permissions'])->insert($permission);
            $permissionIds[] = $id;
        }

        // 4a. Initialize Account Caps (Two-Tier Delegation)
        // Without this, no user (including Tenant Zeus) can delegate permissions.
        $accountAssignedPermissions = [];
        foreach ($accountIds as $accountId) {
            foreach ($permissionIds as $permId) {
                $accountAssignedPermissions[] = [
                    'id' => (string) Str::ulid(),
                    'account_id' => $accountId,      // the tenant context
                    'permission_id' => $permId,
                    'assignee_id' => $accountId,     // assigned heavily to the account itself
                    'assignee_type' => 'account',
                    'grantable' => true,             // THIS MAKES IT THE ACCOUNT CAP
                    'access' => null,                // null = full access allowed
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach ($accountAssignedPermissions as $assignment) {
            DB::table($tables['assigned_permissions'])->insert($assignment);
        }

        // 4b. Assign Permissions to Roles via assigned_permissions table
        $assignedPermissions = [];

        // Super Admin gets all permissions (null access = full)
        foreach ($permissionIds as $permId) {
            $assignedPermissions[] = [
                'account_id' => null, // System-wide
                'permission_id' => $permId,
                'assignee_id' => $roleIds[0], // Super Admin role
                'assignee_type' => 'role',
                'access' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Helper Map to quickly find permission IDs by name
        $permMap = [];
        foreach ($permissions as $index => $perm) {
             $permMap[$perm['name']] = $permissionIds[$index];
        }

        // Add some explicit role assignments for testing

        // Pharmacy Manager (MediCare)
        $managerPerms = [
            'dashboard.view' => ['on'],
            'users.manage' => ['create', 'read', 'update'],
            'inventory.manage' => ['create', 'read', 'update', 'delete'],
            'pos.access' => ['on'],
            'sales.process' => ['create', 'read', 'update', 'delete'],
        ];

        foreach ($managerPerms as $name => $access) {
            if (isset($permMap[$name])) {
                $assignedPermissions[] = [
                    'account_id' => $accountIds[0], // MediCare Pharmacy
                    'permission_id' => $permMap[$name],
                    'assignee_id' => $roleIds[2], // MediCare Pharmacy Manager role
                    'assignee_type' => 'role',
                    'access' => json_encode($access),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Licensed Pharmacist (MediCare)
        $pharmacistPerms = [
            'dashboard.view' => ['on'],
            'inventory.manage' => ['read'],
            'pos.access' => ['on'],
            'sales.process' => ['create', 'read'],
        ];

        foreach ($pharmacistPerms as $name => $access) {
            if (isset($permMap[$name])) {
                $assignedPermissions[] = [
                    'account_id' => $accountIds[0], // MediCare Pharmacy
                    'permission_id' => $permMap[$name],
                    'assignee_id' => $roleIds[3], // MediCare Licensed Pharmacist
                    'assignee_type' => 'role',
                    'access' => json_encode($access),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                // And for QuickRx Pharmacist Role
                $assignedPermissions[] = [
                    'account_id' => $accountIds[1], // QuickRx Pharmacy
                    'permission_id' => $permMap[$name],
                    'assignee_id' => $roleIds[7], // QuickRx Pharmacist
                    'assignee_type' => 'role',
                    'access' => json_encode($access),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Direct user permission assignment (pharmacist can view audit trail directly)
        if (isset($permMap['audit_trail.view'])) {
            $assignedPermissions[] = [
                'account_id' => $accountIds[0],
                'permission_id' => $permMap['audit_trail.view'],
                'assignee_id' => $pharmacistId,
                'assignee_type' => 'user',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($assignedPermissions as $assignment) {
            $assignment['id'] = (string) Str::ulid();
            DB::table($tables['assigned_permissions'])->insert($assignment);
        }

        // 5. Assign Users to Accounts (Membership)
        $accountUserAssignments = [
            [
                'user_id' => $systemAdminId,
                'account_id' => $accountIds[0], // MediCare
            ],
            [
                'user_id' => $pharmacistId,
                'account_id' => $accountIds[0], // MediCare
            ],
            [
                'user_id' => $customerServiceId,
                'account_id' => $accountIds[0], // MediCare
            ],
            [
                'user_id' => $pharmacistId,
                'account_id' => $accountIds[1], // QuickRx (same pharmacist works at multiple pharmacies)
            ],
        ];

        foreach ($accountUserAssignments as $assignment) {
            DB::table($tables['account_user'])->insert($assignment);
        }

        // 6. Assign Roles to Users
        $roleUserAssignments = [
            // System Admin gets Super Admin role
            [
                'user_id' => $systemAdminId,
                'role_id' => $roleIds[0], // Super Admin
            ],
            // Pharmacist gets Licensed Pharmacist role at MediCare
            [
                'user_id' => $pharmacistId,
                'role_id' => $roleIds[3], // MediCare Licensed Pharmacist
            ],
            // Pharmacist also gets Pharmacist role at QuickRx
            [
                'user_id' => $pharmacistId,
                'role_id' => $roleIds[7], // QuickRx Pharmacist
            ],
            // Customer Service gets Customer Service role
            [
                'user_id' => $customerServiceId,
                'role_id' => $roleIds[5], // MediCare Customer Service
            ],
        ];

        foreach ($roleUserAssignments as $assignment) {
            DB::table('role_user')->insert($assignment);
        }

        // 8. Create sample activity logs
        $activityLogs = [
            [
                'tenant_id' => $accountIds[0],
                'event' => 'role.assigned',
                'causer_id' => $systemAdminId,
                'causer_type' => 'App\Models\User',
                'subject_id' => $pharmacistId,
                'subject_type' => 'App\Models\User',
                'properties' => json_encode([
                    'role' => 'Licensed Pharmacist',
                    'pharmacy' => 'MediCare Pharmacy'
                ]),
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'tenant_id' => $accountIds[0],
                'event' => 'permission.granted',
                'causer_id' => $systemAdminId,
                'causer_type' => 'App\Models\User',
                'subject_id' => $permMap['reports.view'] ?? $permissionIds[0],
                'subject_type' => 'App\Models\Permission',
                'properties' => json_encode([
                    'permission' => 'reports.view',
                    'user' => 'John Pharmacist',
                    'scope' => 'MediCare Pharmacy'
                ]),
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ],
            [
                'tenant_id' => $accountIds[1],
                'event' => 'user.joined',
                'causer_id' => $pharmacistId,
                'causer_type' => 'App\Models\User',
                'subject_id' => $accountIds[1],
                'subject_type' => 'App\Models\Account',
                'properties' => json_encode([
                    'pharmacy' => 'QuickRx Pharmacy',
                    'position' => 'Pharmacist'
                ]),
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
            ],
        ];

        foreach ($activityLogs as $log) {
            $log['id'] = (string) Str::ulid();
            DB::table($tables['activity_logs'])->insert($log);
        }

        $this->command->info('ABAC Permission System seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- 3 Pharmacy accounts');
        $this->command->info('- 8 Roles (including system roles)');
        $this->command->info('- ' . count($permissions) . ' Permissions');
        $this->command->info('- ' . count($assignedPermissions) . ' Assigned permissions (role and user assignments)');
        $this->command->info('- User-account memberships');
        $this->command->info('- User-role assignments');
        $this->command->info('- Sample activity logs');

        $this->command->info("\nTest Users:");
        $this->command->info("1. System Admin: admin@pharmacy.com / password (Super Admin role)");
        $this->command->info("2. Pharmacist: pharmacist@pharmacy.com / password (Works at MediCare & QuickRx)");
        $this->command->info("3. Customer Service: support@pharmacy.com / password");
    }
}