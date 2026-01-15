<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Get existing accounts and users from the ABAC system
        $accounts = DB::table('accounts')->pluck('id')->toArray();
        $users = DB::table('users')->pluck('id')->toArray();

        // Get pharmacy-specific user IDs for realistic assignments
        $systemAdminId = DB::table('users')->where('email', 'admin@pharmacy.com')->value('id');
        $pharmacistId = DB::table('users')->where('email', 'pharmacist@pharmacy.com')->value('id');
        $customerServiceId = DB::table('users')->where('email', 'support@pharmacy.com')->value('id');

        // Clear existing data in correct order (reverse of creation)
        // DB::table('event_rejections')->delete();
        // DB::table('audit_trail')->delete();
        // DB::table('tax_rates')->delete();
        // DB::table('batches')->delete();
        // DB::table('drugs')->delete();
        // DB::table('financial_day_summaries')->delete();
        // DB::table('sale_items')->delete();
        // DB::table('inventory')->delete();
        // DB::table('sales')->delete();
        // DB::table('event_ledger')->delete();
        // DB::table('devices')->delete();
 // Disable foreign key checks for SQLite
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        }

        // Clear existing data in correct order (children first, then parents)
        DB::table('event_rejections')->truncate();
        DB::table('audit_trail')->truncate();
        DB::table('sale_items')->truncate();
        DB::table('financial_day_summaries')->truncate();
        DB::table('inventory')->truncate();
        DB::table('sales')->truncate();
        DB::table('tax_rates')->truncate();
        DB::table('batches')->truncate();
        DB::table('drugs')->truncate();
        DB::table('event_ledger')->truncate();
        DB::table('devices')->truncate();

        // Re-enable foreign key checks
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        }
        // 1. Create Devices for each pharmacy
        $deviceIds = [];
        foreach ($accounts as $accountId) {
            for ($d = 0; $d < rand(2, 4); $d++) {
                $deviceId = (string) Str::uuid();
                DB::table('devices')->insert([
                    'device_id' => $deviceId,
                    'device_name' => $faker->randomElement(['Main Counter', 'Drive-thru', 'Consultation', 'Stockroom']) . ' ' . $faker->randomElement(['POS', 'Scanner', 'Tablet']),
                    'trust_status' => 'active',
                    'account_id' => $accountId,
                    'device_type' => $faker->randomElement(['POS', 'Mobile', 'Tablet', 'Kiosk']),
                    'serial_number' => $faker->bothify('SN-########'),
                    'mac_address' => $faker->macAddress,
                    'created_at' => now()->subDays(rand(30, 180)),
                    'updated_at' => now(),
                ]);
                $deviceIds[] = $deviceId;
            }
        }

        // 2. Create Event Ledger entries
        foreach ($deviceIds as $deviceId) {
            $accountId = DB::table('devices')->where('device_id', $deviceId)->value('account_id');
            $eventCount = rand(5, 15);

            for ($e = 0; $e < $eventCount; $e++) {
                DB::table('event_ledger')->insert([
                    'id' => (string) Str::uuid(),
                    'account_id' => $accountId,
                    'device_id' => $deviceId,
                    'actor_user_id' => $faker->optional(0.8)->randomElement($users),
                    'event_type' => $faker->randomElement(['sale.created', 'inventory.updated', 'user.logged_in', 'device.registered']),
                    'event_category' => $faker->randomElement(['sale', 'inventory', 'audit', 'system']),
                    'event_version' => 1,
                    'event_payload' => json_encode([
                        'action' => $faker->sentence(3),
                        'details' => $faker->sentence(10),
                        'timestamp' => now()->toDateTimeString()
                    ]),
                    'local_sequence' => $e + 1,
                    'event_time_utc' => now()->subHours(rand(1, 48)),
                    'event_hash' => Str::random(64),
                    'received_at_cloud' => now()->subHours(rand(0, 24)),
                    'metadata' => json_encode(['source' => 'device', 'priority' => 'normal']),
                    'created_at' => now()->subHours(rand(1, 48)),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Create Drugs (common pharmacy drugs)
        $drugIds = [];
        $commonDrugs = [
            ['name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'strength' => '500mg', 'form' => 'Capsule', 'drug_class' => 'Antibiotic'],
            ['name' => 'Lisinopril', 'generic_name' => 'Lisinopril', 'strength' => '10mg', 'form' => 'Tablet', 'drug_class' => 'Blood Pressure'],
            ['name' => 'Metformin', 'generic_name' => 'Metformin', 'strength' => '850mg', 'form' => 'Tablet', 'drug_class' => 'Diabetes'],
            ['name' => 'Atorvastatin', 'generic_name' => 'Atorvastatin', 'strength' => '20mg', 'form' => 'Tablet', 'drug_class' => 'Cholesterol'],
            ['name' => 'Levothyroxine', 'generic_name' => 'Levothyroxine', 'strength' => '50mcg', 'form' => 'Tablet', 'drug_class' => 'Thyroid'],
            ['name' => 'Albuterol', 'generic_name' => 'Albuterol', 'strength' => '90mcg', 'form' => 'Inhaler', 'drug_class' => 'Asthma'],
            ['name' => 'Omeprazole', 'generic_name' => 'Omeprazole', 'strength' => '20mg', 'form' => 'Capsule', 'drug_class' => 'Acid Reflux'],
            ['name' => 'Sertraline', 'generic_name' => 'Sertraline', 'strength' => '50mg', 'form' => 'Tablet', 'drug_class' => 'Antidepressant'],
            ['name' => 'Ibuprofen', 'generic_name' => 'Ibuprofen', 'strength' => '400mg', 'form' => 'Tablet', 'drug_class' => 'Pain Relief', 'is_prescription' => false],
            ['name' => 'Acetaminophen', 'generic_name' => 'Acetaminophen', 'strength' => '500mg', 'form' => 'Tablet', 'drug_class' => 'Pain Relief', 'is_prescription' => false],
            ['name' => 'Vitamin D3', 'generic_name' => 'Cholecalciferol', 'strength' => '1000 IU', 'form' => 'Softgel', 'drug_class' => 'Supplement', 'is_prescription' => false],
            ['name' => 'Oxycodone', 'generic_name' => 'Oxycodone', 'strength' => '5mg', 'form' => 'Tablet', 'drug_class' => 'Pain Relief', 'is_controlled' => true, 'is_narcotic' => true, 'schedule' => 'CII'],
            ['name' => 'Alprazolam', 'generic_name' => 'Alprazolam', 'strength' => '0.5mg', 'form' => 'Tablet', 'drug_class' => 'Anxiety', 'is_controlled' => true, 'schedule' => 'CIV'],
        ];

        foreach ($commonDrugs as $drug) {
            $drugId = (string) Str::uuid();
            DB::table('drugs')->insert([
                'id' => $drugId,
                'name' => $drug['name'],
                'generic_name' => $drug['generic_name'],
                'strength' => $drug['strength'],
                'form' => $drug['form'],
                'route' => $faker->randomElement(['Oral', 'Topical', 'Inhalation']),
                'regulatory_code' => $faker->bothify('NDC-#####-##'),
                'manufacturer' => $faker->randomElement(['Pfizer', 'Novartis', 'Merck', 'GSK']),
                'supplier' => $faker->company,
                'is_prescription' => $drug['is_prescription'] ?? true,
                'is_controlled' => $drug['is_controlled'] ?? false,
                'is_narcotic' => $drug['is_narcotic'] ?? false,
                'drug_class' => $drug['drug_class'],
                'storage_conditions' => $faker->randomElement(['Room Temperature', 'Refrigerate']),
                'description' => $faker->sentence(10),
                'side_effects' => $faker->sentence(15),
                'contraindications' => $faker->sentence(12),
                'schedule' => $drug['schedule'] ?? null,
                'alternate_names' => json_encode([$drug['name'] . ' ER']),
                'metadata' => json_encode(['category' => $drug['drug_class']]),
                'created_at' => now()->subDays(rand(100, 365)),
                'updated_at' => now(),
            ]);
            $drugIds[] = $drugId;
        }

        // 4. Create Batches for drugs
        $batchIds = [];
        foreach ($drugIds as $drugId) {
            for ($b = 0; $b < rand(1, 3); $b++) {
                $batchId = (string) Str::uuid();
                $manufactureDate = $faker->dateTimeBetween('-1 year', '-3 months');
                $expiryDate = (clone $manufactureDate)->modify('+' . rand(12, 24) . ' months');

                DB::table('batches')->insert([
                    'id' => $batchId,
                    'drug_id' => $drugId,
                    'manufacture_date' => $manufactureDate->format('Y-m-d'),
                    'manufacturer' => $faker->company,
                    'supplier' => $faker->company,
                    'received_date' => $faker->dateTimeBetween($manufactureDate, '+1 month')->format('Y-m-d'),
                    'is_active' => true,
                    'expiry_date' => $expiryDate->format('Y-m-d'),
                    'lot_number' => $faker->bothify('LOT-####'),
                    'quantity' => $faker->numberBetween(500, 5000),
                    'quantity_recieved' => $faker->numberBetween(500, 5000),
                    'cost_price' => $faker->randomFloat(2, 0.50, 50.00),
                    'name' => $faker->optional(0.3)->word . ' Batch',
                    'storage_location' => $faker->randomElement(['Shelf A', 'Refrigerator', 'Room Temp']),
                    'created_at' => $manufactureDate,
                    'updated_at' => now(),
                ]);
                $batchIds[] = $batchId;
            }
        }
      $inventoryIds = [];
        foreach ($accounts as $accountId) {
            $pharmacyDrugs = $faker->randomElements($drugIds, rand(8, 12));

            foreach ($pharmacyDrugs as $drugId) {
                $batchId = $faker->randomElement($batchIds);
                $inventoryId = (string) Str::uuid();
                $costPrice = DB::table('batches')->where('id', $batchId)->value('cost_price');
                $sellingPrice = $costPrice * $faker->randomFloat(2, 1.3, 2.0);

                DB::table('inventory')->insert([
                    'id' => $inventoryId,
                    'account_id' => $accountId,
                    'drug_id' => $drugId,
                    'batch_id' => $batchId,
                    'selling_price' => round($sellingPrice, 2),
                    'cost_price' => $costPrice,
                    'reorder_level' => $faker->numberBetween(20, 100),
                    'location' => $faker->randomElement(['Shelf A1', 'Shelf B3', 'Refrigerator 1']),
                    'is_active' => true,
                    'quantity_on_hand' => $quantity = $faker->numberBetween(50, 500),
                    'created_at' => now()->subDays(rand(1, 90)),
                    'updated_at' => now(),
                ]);
                $inventoryIds[] = $inventoryId;
            }
        }

        // 6. Create Sales for each pharmacy
        $saleIds = [];
        foreach ($accounts as $accountId) {
            $salesCount = rand(10, 20);

            for ($s = 0; $s < $salesCount; $s++) {
                $saleId = (string) Str::uuid();
                $subtotal = $faker->randomFloat(2, 10, 300);
                $tax = round($subtotal * 0.07, 2);
                $total = $subtotal + $tax;

                DB::table('sales')->insert([
                    'id' => $saleId,
                    'account_id' => $accountId,
                    'user_id' => $faker->randomElement([$pharmacistId, $customerServiceId]),
                    'customer_name' => $faker->name,
                    // 'customer_dob' => $faker->optional(0.6)->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
                    'customer_phone' => $faker->optional(0.8)->phoneNumber,
                    'customer_email' => $faker->optional(0.5)->email,
                    'subtotal_amount' => $subtotal,
                    'tax_amount' => $tax,
                    'total_amount' => $total,
                    'payment_type' => $faker->randomElement(['cash', 'card', 'insurance', 'momo']),
                    'payment_metadata' => json_encode([
                        'method' => $faker->randomElement(['Visa', 'Mastercard', 'Cash']),
                        'last4' => $faker->optional(0.5)->bothify('####'),
                        'insurance_provider' => $faker->optional(0.3)->randomElement(['GlicoHealth', 'Metropolitan', 'Accuhealth'])
                    ]),
                    'finalized_at' => $faker->dateTimeBetween('-7 days', 'now'),
                    'created_at' => $faker->dateTimeBetween('-7 days', 'now'),
                    'updated_at' => now(),
                ]);
                $saleIds[] = $saleId;
            }
        }

        // 7. Create Sale Items
        foreach ($saleIds as $saleId) {
            $itemCount = rand(1, 5);
            $inventoryItems = $faker->randomElements($inventoryIds, min($itemCount, count($inventoryIds)));

            foreach ($inventoryItems as $inventoryId) {
                $inventory = DB::table('inventory')->where('id', $inventoryId)->first();
                $batch = DB::table('batches')->where('id', $inventory->batch_id)->first();
                $drug = DB::table('drugs')->where('drug_id', $inventory->drug_id)->first(); // Changed from 'id' to 'drug_id'

                $quantity = $faker->numberBetween(1, 3);
                $unitPrice = $inventory->selling_price;
                $lineTotal = $quantity * $unitPrice;
                $taxAmount = round($lineTotal * 0.07, 2);

                DB::table('sale_items')->insert([
                    'id' => (string) Str::uuid(),
                    'batch_id' => $inventory->batch_id,
                    'inventory_id' => $inventoryId,
                    'sale_id' => $saleId,
                    'drug_id' => $inventory->drug_id,
                    'quantity' => $quantity,
                    'price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'tax_amount' => $taxAmount,
                    'requires_prescription' => $drug ? $drug->is_prescription : false,
                    'prescription_metadata' => $drug && $drug->is_prescription ? json_encode([
                        'verified' => true,
                        'verified_by' => $faker->randomElement([$pharmacistId]),
                        'verified_at' => now()->toDateTimeString()
                    ]) : null,
                    'dosage_instructions' => $drug && $drug->is_prescription ? json_encode([
                        'frequency' => $faker->randomElement(['Once daily', 'Twice daily', 'Three times daily']),
                        'duration' => $faker->randomElement(['7 days', '14 days', '30 days']),
                        'with_food' => $faker->boolean(70)
                    ]) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 8. Create Financial Day Summaries for last 30 days
        foreach ($accounts as $accountId) {
            for ($day = 0; $day < 30; $day++) {
                $date = now()->subDays($day);

                $grossSales = $faker->randomFloat(2, 500, 3000);
                $totalCost = $grossSales * 0.6;
                $taxCollected = $grossSales * 0.07;
                $totalDiscounts = $grossSales * 0.05;
                $netSales = $grossSales - $totalDiscounts;
                $grossProfit = $netSales - $totalCost;

                DB::table('financial_day_summaries')->insert([
                    'id' => (string) Str::uuid(),
                    'account_id' => $accountId,
                    'day' => $date->toDateString(),
                    'gross_sales' => $grossSales,
                    'net_sales' => $netSales,
                    'tax_collected' => $taxCollected,
                    'total_discounts' => $totalDiscounts,
                    'total_cost' => $totalCost,
                    'gross_profit' => $grossProfit,
                    'total_transactions' => rand(20, 50),
                    'prescription_count' => rand(5, 20),
                    'otc_count' => rand(15, 40),
                    'cash_collected' => $grossSales * 0.4,
                    'card_collected' => $grossSales * 0.4,
                    'insurance_billed' => $grossSales * 0.2,
                    'returns_amount' => $faker->randomFloat(2, 10, 100),
                    'customer_count' => rand(25, 45),
                    'payment_breakdown' => json_encode([
                        'cash' => 40,
                        'card' => 40,
                        'insurance' => 20
                    ]),
                    'category_breakdown' => json_encode([
                        'Antibiotics' => 15,
                        'Pain Relief' => 25,
                        'Chronic Conditions' => 35,
                        'OTC' => 25
                    ]),
                    'is_closed' => $date->isBefore(now()->subDay()),
                    'closed_at' => $date->isBefore(now()->subDay()) ? $date->copy()->setTime(23, 59, 59) : null,
                    'closed_by_user_id' => $date->isBefore(now()->subDay()) ? $systemAdminId : null,
                    'created_at' => $date,
                    'updated_at' => now(),
                ]);
            }
        }

        // 9. Create Tax Rates
        $jurisdictions = ['State A', 'State B', 'State C', 'County X', 'City Y'];

        foreach ($jurisdictions as $jurisdiction) {
            // Global tax rate
            DB::table('tax_rates')->insert([
                'id' => (string) Str::uuid(),
                'account_id' => null,
                'jurisdiction' => $jurisdiction,
                'tax_name' => $jurisdiction . ' Sales Tax',
                'percentage' => $faker->randomFloat(2, 5, 10),
                'tax_type' => 'sales',
                'applicable_categories' => json_encode(['all']),
                'description' => 'Standard sales tax for ' . $jurisdiction,
                'effective_from' => '2023-01-01',
                'is_active' => true,
                'effective_to' => null,
                'created_at' => now()->subDays(rand(100, 365)),
                'updated_at' => now(),
            ]);

            // Pharmacy-specific tax rates
            foreach ($accounts as $accountId) {
                if ($faker->boolean(50)) {
                    DB::table('tax_rates')->insert([
                        'id' => (string) Str::uuid(),
                        'account_id' => $accountId,
                        'jurisdiction' => $jurisdiction,
                        'tax_name' => 'Pharmacy Special Tax',
                        'percentage' => $faker->randomFloat(2, 1, 3),
                        'tax_type' => 'pharmacy',
                        'applicable_categories' => json_encode(['prescription', 'controlled']),
                        'description' => 'Special pharmacy tax for controlled substances',
                        'effective_from' => '2023-06-01',
                        'is_active' => true,
                        'effective_to' => null,
                        'created_at' => now()->subDays(rand(50, 200)),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 10. Create Audit Trail
        for ($a = 0; $a < 50; $a++) {
            $accountId = $faker->randomElement($accounts);
            $entityType = $faker->randomElement(['sale', 'inventory', 'drug', 'batch', 'user']);
            $entityId = match($entityType) {
                'sale' => $faker->randomElement($saleIds),
                'inventory' => $faker->randomElement($inventoryIds),
                'drug' => $faker->randomElement($drugIds),
                'batch' => $faker->randomElement($batchIds),
                default => (string) Str::uuid(),
            };

            $oldValues = $faker->boolean(70) ? json_encode(['old_value' => $faker->word]) : null;
            $newValues = json_encode(['new_value' => $faker->word]);

            DB::table('audit_trail')->insert([
                'id' => (string) Str::uuid(),
                'account_id' => $accountId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $faker->randomElement(['created', 'updated', 'deleted', 'verified']),
                'actor_user_id' => $faker->randomElement($users),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'timestamp' => $faker->dateTimeBetween('-30 days', 'now'),
                'metadata' => json_encode(['ip' => $faker->ipv4, 'user_agent' => $faker->userAgent]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 11. Create Event Rejections
        for ($r = 0; $r < 5; $r++) {
            $eventId = (string) Str::uuid();
            DB::table('event_rejections')->insert([
                'id' => $eventId,
                'rejection_reason' => $faker->randomElement(['Invalid format', 'Missing required fields', 'Duplicate event', 'Data inconsistency']),
                'reviewed_by' => $faker->optional(0.7)->randomElement([$systemAdminId]),
                'rejection_details' => $faker->sentence(15),
                'resolved_at' => $faker->optional(0.6)->dateTimeBetween('-10 days', 'now'),
                'resolution_action' => $faker->randomElement(['corrected', 'ignored', 'resubmitted']),
                'resolved_event_id' => $faker->boolean(50) ? (string) Str::uuid() : null,
                'correction_data' => json_encode(['corrected_fields' => $faker->words(3, true)]),
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Pharmacy Platform Data seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . count($deviceIds) . ' Devices');
        $this->command->info('- ' . count($drugIds) . ' Drugs');
        $this->command->info('- ' . count($batchIds) . ' Batches');
        $this->command->info('- ' . count($inventoryIds) . ' Inventory items');
        $this->command->info('- ' . count($saleIds) . ' Sales');
        $this->command->info('- 30 days of financial summaries per pharmacy');
        $this->command->info('- Tax rates for multiple jurisdictions');
        $this->command->info('- 50+ audit trail entries');
        $this->command->info('- 5 event rejection records');
    }
}