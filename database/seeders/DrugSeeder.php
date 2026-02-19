<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DrugSeeder extends Seeder
{
    public function run()
    {
        $path = database_path('data/drugs.json');

        if (!File::exists($path)) {
            $this->command->error("Data file not found: $path");
            $this->command->warn("Please ensure database/data/drugs.json exists.");
            return;
        }

        // Increase memory limit for processing large JSON
        ini_set('memory_limit', '512M');

        $this->command->info("Reading drugs data from JSON...");
        $json = File::get($path);

        // Decode as associative array
        $drugs = json_decode($json, true);

        if (!$drugs) {
            $this->command->error("Failed to decode JSON or file is empty.");
            return;
        }

        $total = count($drugs);
        $this->command->info("Found {$total} records. Starting insertion...");

        $batchSize = 500;
        $chunks = array_chunk($drugs, $batchSize);
        $count = 0;

        foreach ($chunks as $chunk) {
            foreach ($chunk as &$drug) {
                // Generate ID
                $drug['id'] = (string) Str::ucfirst(Str::ulid());

                // Ensure nullable fields are handled
                $drug['name'] = Str::ucfirst($drug['name']);
                $drug['generic_name'] = Str::ucfirst($drug['generic_name']);
                $drug['route'] = $drug['route'] ?? null;
                $drug['regulatory_code'] = $drug['regulatory_code'] ?? null;
                $drug['supplier'] = $drug['supplier'] ?? null;
                $drug['drug_class'] = $drug['drug_class'] ?? null;
                $drug['storage_conditions'] = $drug['storage_conditions'] ?? null;
                $drug['description'] = $drug['description'] ?? null;
            }

            DB::table('drugs')->insert($chunk);

            $count += count($chunk);
            $this->command->info("Inserted {$count}/{$total} records...");
        }

        $this->command->info("DrugSeeder completed successfully!");
    }
}
