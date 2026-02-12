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
        // YouTube Admin User
        // YouTube Admin User
        User::firstOrCreate(
            ['email' => 'youtube@admin.com', 'service_type' => 'youtube'],
            [
                'name' => 'YouTube Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'is_admin' => true,
            ]
        );

        // Zillow Admin User
        User::firstOrCreate(
            ['email' => 'zillow@admin.com', 'service_type' => 'zillow'],
            [
                'name' => 'Zillow Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'is_admin' => true,
            ]
        );
    }
}
