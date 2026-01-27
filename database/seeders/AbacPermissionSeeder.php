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
            // Product Management
            [
                'name' => 'products.manage',
                'type' => 'crud',
                'description' => 'Manage all pharmaceutical products',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'products.view',
                'type' => 'on-off',
                'description' => 'View product catalog',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'inventory.manage',
                'type' => 'crud',
                'description' => 'Manage inventory levels and stock',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'prescription.verify',
                'type' => 'on-off',
                'description' => 'Verify and validate prescriptions',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'prescription.dispense',
                'type' => 'on-off',
                'description' => 'Dispense prescription medications',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Order Management
            [
                'name' => 'orders.process',
                'type' => 'crud',
                'description' => 'Process customer orders',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'orders.delivery',
                'type' => 'on-off',
                'description' => 'Manage order delivery and shipping',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'orders.pickup',
                'type' => 'on-off',
                'description' => 'Handle in-store pickups',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Customer Management
            [
                'name' => 'customers.manage',
                'type' => 'crud',
                'description' => 'Manage customer profiles and data',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'medical_records.view',
                'type' => 'on-off',
                'description' => 'View customer medical records (HIPAA compliant)',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Pharmacy Operations
            [
                'name' => 'pharmacy.settings',
                'type' => 'crud',
                'description' => 'Manage pharmacy settings and configuration',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'insurance.billing',
                'type' => 'crud',
                'description' => 'Process insurance claims and billing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'reports.generate',
                'type' => 'on-off',
                'description' => 'Generate pharmacy reports and analytics',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'compliance.manage',
                'type' => 'on-off',
                'description' => 'Manage regulatory compliance',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // User & Role Management
            [
                'name' => 'users.manage',
                'type' => 'crud',
                'description' => 'Manage pharmacy staff users',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'roles.manage',
                'type' => 'crud',
                'description' => 'Manage roles and permissions',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Financial
            [
                'name' => 'financial.view',
                'type' => 'on-off',
                'description' => 'View financial reports',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'financial.manage',
                'type' => 'crud',
                'description' => 'Manage all financial operations',
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

        // 4. Assign Permissions to Roles via assigned_permissions table
        $assignedPermissions = [
            // Super Admin (system-wide role) gets all permissions
            [
                'account_id' => null, // System-wide
                'permission_id' => $permissionIds[0], // products.manage
                'assignee_id' => $roleIds[0], // Super Admin role
                'assignee_type' => 'role',
                'access' => json_encode(['create', 'read', 'update', 'delete']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => null,
                'permission_id' => $permissionIds[1], // products.view
                'assignee_id' => $roleIds[0],
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => null,
                'permission_id' => $permissionIds[2], // inventory.manage
                'assignee_id' => $roleIds[0],
                'assignee_type' => 'role',
                'access' => json_encode(['create', 'read', 'update', 'delete']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Pharmacy Manager (MediCare) - account-specific permissions
            [
                'account_id' => $accountIds[0], // MediCare Pharmacy
                'permission_id' => $permissionIds[0], // products.manage
                'assignee_id' => $roleIds[2], // MediCare Pharmacy Manager role
                'assignee_type' => 'role',
                'access' => json_encode(['create', 'read', 'update']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[0],
                'permission_id' => $permissionIds[3], // prescription.verify
                'assignee_id' => $roleIds[2],
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[0],
                'permission_id' => $permissionIds[4], // prescription.dispense
                'assignee_id' => $roleIds[2],
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Licensed Pharmacist (MediCare)
            [
                'account_id' => $accountIds[0],
                'permission_id' => $permissionIds[1], // products.view
                'assignee_id' => $roleIds[3], // MediCare Licensed Pharmacist
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[0],
                'permission_id' => $permissionIds[3], // prescription.verify
                'assignee_id' => $roleIds[3],
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[0],
                'permission_id' => $permissionIds[4], // prescription.dispense
                'assignee_id' => $roleIds[3],
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Customer Service (MediCare)
            [
                'account_id' => $accountIds[0],
                'permission_id' => $permissionIds[1], // products.view
                'assignee_id' => $roleIds[5], // MediCare Customer Service
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[0],
                'permission_id' => $permissionIds[5], // orders.process
                'assignee_id' => $roleIds[5],
                'assignee_type' => 'role',
                'access' => json_encode(['create', 'read', 'update']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Direct user permission assignment (pharmacist can view medical records at MediCare)
            [
                'account_id' => $accountIds[0],
                'permission_id' => $permissionIds[9], // medical_records.view
                'assignee_id' => $pharmacistId,
                'assignee_type' => 'user',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // QuickRx Pharmacist
            [
                'account_id' => $accountIds[1], // QuickRx Pharmacy
                'permission_id' => $permissionIds[1], // products.view
                'assignee_id' => $roleIds[7], // QuickRx Pharmacist
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $accountIds[1],
                'permission_id' => $permissionIds[3], // prescription.verify
                'assignee_id' => $roleIds[7],
                'assignee_type' => 'role',
                'access' => json_encode(['on']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

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
                'subject_id' => $permissionIds[9],
                'subject_type' => 'App\Models\Permission',
                'properties' => json_encode([
                    'permission' => 'medical_records.view',
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
        $this->command->info('- 19 Permissions');
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