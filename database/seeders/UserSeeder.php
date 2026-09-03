<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_ADMIN_PASSWORD');

        if (! $password) {
            // Fail closed in production: never seed a weak/default password.
            if (app()->isProduction()) {
                throw new \RuntimeException('SEED_ADMIN_PASSWORD must be set in production.');
            }

            $password = 'password';
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@monitoring.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
            ]
        );

        // `role` is intentionally NOT mass-assignable — assign explicitly.
        if (! $admin->isAdmin()) {
            $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        }
    }
}
