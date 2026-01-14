<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Hash;

class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Create Tenants (5)
        $tenants = [];
        for ($i = 0; $i < 5; $i++) {
            $tenantId = (string) Str::uuid();
            DB::table('tenants')->insert([
                'tenant_id' => $tenantId,
                'legal_name' => $faker->company,
                'tax_identifier' => $faker->unique()->ean13,
                'regulatory_metadata' => json_encode(['region' => $faker->state]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $tenants[] = $tenantId;
        }

        // Create Branches (2 per tenant)
        $branches = [];
        foreach ($tenants as $tenantId) {
            for ($j = 0; $j < 2; $j++) {
                $branchId = (string) Str::uuid();
                DB::table('branches')->insert([
                    'branch_id' => $branchId,
                    'tenant_id' => $tenantId,
                    'branch_name' => $faker->city . ' Branch',
                    'physical_address' => $faker->address,
                    'license_number' => $faker->unique()->bothify('LIC-####'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $branches[] = $branchId;
            }
        }

        // Create Devices (2 per branch)
        foreach ($branches as $branchId) {
            for ($k = 0; $k < 2; $k++) {
                DB::table('devices')->insert([
                    'device_id' => (string) Str::uuid(),
                    'branch_id' => $branchId,
                    'device_name' => $faker->word . '-device',
                    'trust_status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Create Roles (3)
        $roleIds = [];
        $roleNames = ['admin', 'manager', 'staff'];
        foreach ($roleNames as $roleName) {
            $roleId = (string) Str::uuid();
            DB::table('roles')->insert([
                'role_id' => $roleId,
                'role_name' => $roleName,
                'permissions' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $roleIds[] = $roleId;
        }

        // Create Drugs (10)
        $drugIds = [];
        for ($d = 0; $d < 10; $d++) {
            $drugId = (string) Str::uuid();
            DB::table('drugs')->insert([
                'drug_id' => $drugId,
                'name' => $faker->word . 'Drug',
                'strength' => $faker->randomElement(['5mg', '10mg', '20mg']),
                'regulatory_code' => $faker->bothify('RC-####'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $drugIds[] = $drugId;
        }

        // Create Batches (20) - two per drug
        $batchIds = [];
        foreach ($drugIds as $drugId) {
            for ($b = 0; $b < 2; $b++) {
                $batchId = (string) Str::uuid();
                DB::table('batches')->insert([
                    'batch_id' => $batchId,
                    'drug_id' => $drugId,
                    'expiry_date' => $faker->dateTimeBetween('+1 year', '+3 years')->format('Y-m-d'),
                    'manufacturer' => $faker->company,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $batchIds[] = $batchId;
            }
        }

        // Create Inventory (30 rows) - random drug/batch per branch
        foreach ($branches as $branchId) {
            for ($i = 0; $i < 3; $i++) { // 3 items per branch => 5 tenants *2 branches*3 = 30
                $drugId = $faker->randomElement($drugIds);
                $batchId = $faker->randomElement($batchIds);
                DB::table('inventory')->insert([
                    'inventory_id' => (string) Str::uuid(),
                    'tenant_id' => $faker->randomElement($tenants),
                    'branch_id' => $branchId,
                    'drug_id' => $drugId,
                    'batch_id' => $batchId,
                    'quantity_on_hand' => $faker->numberBetween(0, 200),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Create Sales (30 rows) - random per branch
        foreach ($branches as $branchId) {
            for ($s = 0; $s < 3; $s++) {
                $total = $faker->randomFloat(2, 10, 500);
                $tax = round($total * 0.07, 2);
                DB::table('sales')->insert([
                    'sale_id' => (string) Str::uuid(),
                    'tenant_id' => $faker->randomElement($tenants),
                    'branch_id' => $branchId,
                    'total_amount' => $total,
                    'tax_amount' => $tax,
                    'payment_type' => $faker->randomElement(['cash', 'card', 'insurance']),
                    'finalized_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Create Tax Rates (5)
        for ($t = 0; $t < 5; $t++) {
            DB::table('tax_rates')->insert([
                'tax_rate_id' => (string) Str::uuid(),
                'jurisdiction' => $faker->state,
                'percentage' => $faker->randomFloat(2, 5, 15),
                'effective_from' => $faker->date(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create Financial Day Summaries (5)
        foreach ($branches as $branchId) {
            DB::table('financial_day_summaries')->insert([
                'summary_id' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'day' => now()->toDateString(),
                'gross_sales' => $faker->randomFloat(2, 1000, 5000),
                'tax_collected' => $faker->randomFloat(2, 70, 350),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create Audit Trail (30 rows)
        for ($a = 0; $a < 30; $a++) {
            DB::table('audit_trail')->insert([
                'audit_id' => (string) Str::uuid(),
                'entity_type' => $faker->randomElement(['sale', 'inventory', 'device', 'user']),
                'entity_id' => (string) Str::uuid(),
                'action' => $faker->randomElement(['created', 'updated', 'deleted']),
                'actor_user_id' => null,
                'timestamp' => now(),
                'metadata' => json_encode(['detail' => $faker->sentence]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // Create Users (at least 15 to ensure coverage) and Pivot Assignments
        for ($u = 0; $u < 15; $u++) {
            $userId = (string) Str::uuid();
            DB::table('users')->insert([
                'user_id' => $userId,
                'name' => $faker->name,
                'email' => $faker->unique()->email,
                'password' => Hash::make('password@1'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign to exactly 1 tenant
            $tenantId = $faker->randomElement($tenants);
            DB::table('tenant_user')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'role_id' => $faker->randomElement($roleIds),
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign to at least 1 branch belonging to that tenant
            $tenantBranches = array_filter($branches, function($branchId) use ($tenantId) {
                // Get branch's tenant_id from DB
                $branch = DB::table('branches')->where('branch_id', $branchId)->first();
                return $branch && $branch->tenant_id === $tenantId;
            });
            // Always assign to at least one branch for the tenant
            $userBranches = $faker->randomElements($tenantBranches, min(2, count($tenantBranches)));
            if (empty($userBranches)) {
                // fallback: assign to any branch if none found (shouldn't happen)
                $userBranches = [$faker->randomElement($branches)];
            }
            foreach ($userBranches as $bId) {
                DB::table('branch_user')->insert([
                    'branch_id' => $bId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
