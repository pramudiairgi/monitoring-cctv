<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'LALIN', 'slug' => 'lalin'],
            ['name' => 'PATROLI', 'slug' => 'patroli'],
            ['name' => 'KANTOR PEMERINTAHAN', 'slug' => 'kantor-pemerintahan'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name']]
            );
        }

        $this->call(UserSeeder::class);
        $this->call(CameraSeeder::class);
        $this->call(SettingsSeeder::class);
    }
}
