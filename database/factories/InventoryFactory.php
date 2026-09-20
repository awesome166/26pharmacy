<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Drug;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quantity_on_hand' => fake()->numberBetween(0, 500),
            'selling_price' => fake()->randomFloat(2, 1, 999),
            'cost_price' => fake()->randomFloat(2, 0.5, 500),
            'reorder_level' => fake()->numberBetween(5, 50),
            'location' => fake()->randomElement(['Shelf A', 'Shelf B', 'Shelf C', 'Fridge', 'Controlled']),
            'is_active' => true,
        ];
    }

    public function forDrug(Drug $drug): static
    {
        return $this->state(fn (array $attributes) => [
            'drug_id' => $drug->id,
        ]);
    }

    public function forBatch(Batch $batch): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => $batch->id,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_on_hand' => fake()->numberBetween(0, 5),
            'reorder_level' => 10,
        ]);
    }
}
