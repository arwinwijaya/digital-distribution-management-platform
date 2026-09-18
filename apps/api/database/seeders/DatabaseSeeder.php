<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password123';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedPlatformOwner();
        $this->seedAdmin();
        $this->seedOutlet();
        $this->seedSupplier();
        $this->seedSales();
        $this->seedDriver();
        $this->seedFinance();
    }

    private function seedPlatformOwner(): void
    {
        User::firstOrCreate(
            ['email' => 'platform_owner@ddp.test'],
            [
                'name' => 'Platform Owner',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'platform_owner',
                'phone' => '081234567890',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Platform Owner seeded: platform_owner@ddp.test / ' . self::DEFAULT_PASSWORD);
    }

    private function seedAdmin(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@ddp.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'admin',
                'phone' => '081234567891',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Admin seeded: admin@ddp.test / ' . self::DEFAULT_PASSWORD);
    }

    private function seedOutlet(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'outlet@ddp.test'],
            [
                'name' => 'Test Outlet',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'outlet',
                'phone' => '081234567892',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Outlet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Toko Test Outlet',
                'phone' => '081234567892',
                'address' => 'Jl. Test No. 1, Jakarta',
                'city' => 'Jakarta',
                'district' => 'Jakarta Pusat',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'is_active' => true,
                'payment_term_days' => 7,
                'category' => 'toko_kelontong',
            ]
        );

        $this->command?->info('Outlet seeded: outlet@ddp.test / ' . self::DEFAULT_PASSWORD);
    }

    private function seedSupplier(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'supplier@ddp.test'],
            [
                'name' => 'Test Supplier',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'supplier',
                'phone' => '081234567893',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Supplier::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'PT Test Supplier',
                'subscription_status' => 'active',
                'subscription_plan' => 'premium',
                'lead_time_days' => 3,
            ]
        );

        $this->command?->info('Supplier seeded: supplier@ddp.test / ' . self::DEFAULT_PASSWORD);
    }

    private function seedSales(): void
    {
        User::firstOrCreate(
            ['email' => 'sales@ddp.test'],
            [
                'name' => 'Test Sales',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'sales',
                'phone' => '081234567894',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Sales seeded: sales@ddp.test / ' . self::DEFAULT_PASSWORD);
    }

    private function seedDriver(): void
    {
        User::firstOrCreate(
            ['email' => 'driver@ddp.test'],
            [
                'name' => 'Test Driver',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'driver',
                'phone' => '081234567895',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Driver seeded: driver@ddp.test / ' . self::DEFAULT_PASSWORD);
    }

    private function seedFinance(): void
    {
        User::firstOrCreate(
            ['email' => 'finance@ddp.test'],
            [
                'name' => 'Test Finance',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'finance',
                'phone' => '081234567896',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Finance seeded: finance@ddp.test / ' . self::DEFAULT_PASSWORD);
    }
}
