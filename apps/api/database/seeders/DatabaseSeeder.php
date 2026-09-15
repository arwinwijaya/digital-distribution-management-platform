<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default admin account: muhamad.arwinwijaya@gmail.com / admin123
        User::firstOrCreate(
            ['email' => 'muhamad.arwinwijaya@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Default admin seeded: muhamad.arwinwijaya@gmail.com / admin123');
    }
}
