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
        User::create([
            'name' => 'YouTube Admin',
            'email' => 'youtube@admin.com',
            'service_type' => 'youtube',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_admin' => true,
        ]);

        // Zillow Admin User
        User::create([
            'name' => 'Zillow Admin',
            'email' => 'zillow@admin.com',
            'service_type' => 'zillow',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_admin' => true,
        ]);
    }
}
