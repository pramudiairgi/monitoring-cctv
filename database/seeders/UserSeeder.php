<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'katasandi';

        $admin = User::firstOrCreate(
            ['email' => 'admin@monitoring.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
            ]
        );

        if (! $admin->isAdmin()) {
            $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole($superAdmin);
        }
    }
}
