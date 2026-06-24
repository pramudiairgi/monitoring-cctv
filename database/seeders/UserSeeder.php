<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'a@a.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('a'),
            ]
        );
    }
}
