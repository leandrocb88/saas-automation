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
        User::create([
            'name' => 'YouTube User',
            'email' => 'youtube@test.com',
            'service_type' => 'youtube',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_admin' => true,
        ]);

        // Zillow Test User
        User::create([
            'name' => 'Zillow User',
            'email' => 'zillow@test.com',
            'service_type' => 'zillow',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
    }
}
