<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_name' => fake()->randomElement(['POS Terminal A', 'POS Terminal B', 'Kiosk', 'Backoffice']) . ' - ' . fake()->bothify('??##'),
            'trust_status' => fake()->randomElement(['active', 'active', 'active', 'pending', 'revoked']),
            'device_type' => fake()->randomElement(['pos', 'kiosk', 'mobile', 'backoffice']),
            'serial_number' => fake()->unique()->bothify('SN-####-????'),
            'mac_address' => fake()->macAddress(),
        ];
    }

    public function trusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'trust_status' => 'active',
        ]);
    }
}
