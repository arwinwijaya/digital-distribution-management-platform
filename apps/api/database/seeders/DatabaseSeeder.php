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
        $users = $this->getUserConfigs();

        foreach ($users as $config) {
            $user = User::firstOrCreate(
                ['email' => $config['email']],
                [
                    'name' => $config['name'],
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'role' => $config['role'],
                    'phone' => $config['phone'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            if (isset($config['outlet'])) {
                $o = $config['outlet'];
                Outlet::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'name' => $o['name'],
                        'phone' => $o['phone'],
                        'address' => $o['address'],
                        'city' => $o['city'],
                        'district' => $o['district'],
                        'latitude' => $o['lat'],
                        'longitude' => $o['lon'],
                        'is_active' => true,
                        'payment_term_days' => 7,
                        'category' => $o['category'] ?? 'toko_kelontong',
                    ]
                );
            }

            if (isset($config['supplier'])) {
                $s = $config['supplier'];
                Supplier::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'name' => $s['name'],
                        'subscription_status' => $s['status'] ?? 'active',
                        'subscription_plan' => $s['plan'] ?? 'premium',
                        'lead_time_days' => $s['lead_time'] ?? 3,
                    ]
                );
            }

            $this->command?->info("Seeded: {$config['name']} <{$config['email']}> [{$config['role']}]");
        }
    }

    /**
     * Centralized user seed definitions. Each entry produces one User row,
     * and optionally associated Outlet / Supplier models.
     *
     * @return list<array{email:string, name:string, role:string, phone:string, outlet?:array, supplier?:array}>
     */
    private function getUserConfigs(): array
    {
        return [
            // ── platform_owner ──────────────────────────────────────
            [
                'email' => 'ahmad.wijaya@ddp.test',
                'name'  => 'Ahmad Wijaya',
                'role'  => 'platform_owner',
                'phone' => '081234567801',
            ],

            // ── admin (2) ──────────────────────────────────────────
            [
                'email' => 'ratna.sari@ddp.test',
                'name'  => 'Ratna Sari',
                'role'  => 'admin',
                'phone' => '081234567802',
            ],
            [
                'email' => 'dimas.pratama@ddp.test',
                'name'  => 'Dimas Pratama',
                'role'  => 'admin',
                'phone' => '081234567803',
            ],

            // ── outlet (2) + associated Outlet model ───────────────
            [
                'email' => 'siti.nurhaliza@ddp.test',
                'name'  => 'Siti Nurhaliza',
                'role'  => 'outlet',
                'phone' => '081234567804',
                'outlet' => [
                    'name'    => 'Toko Siti Jaya',
                    'phone'   => '082112345001',
                    'address' => 'Jl. Merdeka No. 10, Jakarta Selatan',
                    'city'    => 'Jakarta Selatan',
                    'district'=> 'Kebayoran Baru',
                    'lat'     => -6.2431,
                    'lon'     => 106.7962,
                    'category'=> 'toko_kelontong',
                ],
            ],
            [
                'email' => 'budi.santoso@ddp.test',
                'name'  => 'Budi Santoso',
                'role'  => 'outlet',
                'phone' => '081234567805',
                'outlet' => [
                    'name'    => 'Toko Budi Makmur',
                    'phone'   => '082112345002',
                    'address' => 'Jl. Pahlawan No. 25, Bogor',
                    'city'    => 'Bogor',
                    'district'=> 'Bogor Tengah',
                    'lat'     => -6.5971,
                    'lon'     => 106.8060,
                    'category'=> 'minimarket',
                ],
            ],

            // ── supplier (2) + associated Supplier model ───────────
            [
                'email' => 'hendra.kurniawan@ddp.test',
                'name'  => 'Hendra Kurniawan',
                'role'  => 'supplier',
                'phone' => '081234567806',
                'supplier' => [
                    'name'      => 'PT Sumber Pangan Nusantara',
                    'status'    => 'active',
                    'plan'      => 'premium',
                    'lead_time' => 3,
                ],
            ],
            [
                'email' => 'maya.indah@ddp.test',
                'name'  => 'Maya Indah',
                'role'  => 'supplier',
                'phone' => '081234567807',
                'supplier' => [
                    'name'      => 'CV Berkah Distribusi',
                    'status'    => 'active',
                    'plan'      => 'basic',
                    'lead_time' => 5,
                ],
            ],

            // ── sales (3) ──────────────────────────────────────────
            [
                'email' => 'riko.firmansyah@ddp.test',
                'name'  => 'Riko Firmansyah',
                'role'  => 'sales',
                'phone' => '081234567808',
            ],
            [
                'email' => 'anisa.putri@ddp.test',
                'name'  => 'Anisa Putri',
                'role'  => 'sales',
                'phone' => '081234567809',
            ],
            [
                'email' => 'ferry.gunawan@ddp.test',
                'name'  => 'Ferry Gunawan',
                'role'  => 'sales',
                'phone' => '081234567810',
            ],

            // ── driver (3) ─────────────────────────────────────────
            [
                'email' => 'joko.widodo@ddp.test',
                'name'  => 'Joko Widodo',
                'role'  => 'driver',
                'phone' => '081234567811',
            ],
            [
                'email' => 'andi.saputra@ddp.test',
                'name'  => 'Andi Saputra',
                'role'  => 'driver',
                'phone' => '081234567812',
            ],
            [
                'email' => 'rudi.hermawan@ddp.test',
                'name'  => 'Rudi Hermawan',
                'role'  => 'driver',
                'phone' => '081234567813',
            ],

            // ── finance (2) ────────────────────────────────────────
            [
                'email' => 'dewi.lestari@ddp.test',
                'name'  => 'Dewi Lestari',
                'role'  => 'finance',
                'phone' => '081234567814',
            ],
            [
                'email' => 'tono.sugiarto@ddp.test',
                'name'  => 'Tono Sugiarto',
                'role'  => 'finance',
                'phone' => '081234567815',
            ],
        ];
    }
}
