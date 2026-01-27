<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'user_id' =>  \Str::ulid(),
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        //     'password' => 'password@1',

        // ]);

        $this->call(DrugSeeder::class);
        $this->call(AbacPermissionSeeder::class);
        $this->call(PlatformSeeder::class);

    }
}
