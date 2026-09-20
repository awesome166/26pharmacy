<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DrugFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word() . ' ' . fake()->randomElement(['Tablet', 'Capsule', 'Syrup', 'Injection']),
            'generic_name' => fake()->word(),
            'strength' => fake()->randomElement(['10mg', '20mg', '50mg', '100mg', '250mg', '500mg']),
            'form' => fake()->randomElement(['tablet', 'capsule', 'syrup', 'injection', 'cream']),
            'is_prescription' => fake()->boolean(70),
            'is_controlled' => fake()->boolean(10),
            'is_narcotic' => fake()->boolean(5),
        ];
    }

    public function prescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_prescription' => true,
            'is_controlled' => false,
            'is_narcotic' => false,
        ]);
    }

    public function controlled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_prescription' => true,
            'is_controlled' => true,
            'is_narcotic' => fake()->boolean(30),
        ]);
    }
}
