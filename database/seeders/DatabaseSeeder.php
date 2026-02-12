<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // YouTube Test User
        User::factory()->create([
            'name' => 'YouTube User',
            'email' => 'youtube@test.com',
            'service_type' => 'youtube',
            'password' => 'password',
        ]);

        // Zillow Test User
        User::factory()->create([
            'name' => 'Zillow User',
            'email' => 'zillow@test.com',
            'service_type' => 'zillow',
            'password' => 'password',
        ]);
    }
}
