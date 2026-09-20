<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'dob' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
        ];
    }
}
