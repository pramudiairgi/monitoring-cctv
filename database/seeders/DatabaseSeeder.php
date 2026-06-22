<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Traffic', 'slug' => 'traffic'],
            ['name' => 'Public Facility', 'slug' => 'public_facility'],
            ['name' => 'Disaster', 'slug' => 'disaster'],
            ['name' => 'Security', 'slug' => 'security'],
            ['name' => 'Environment', 'slug' => 'environment'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name']]
            );
        }

        $this->call(UserSeeder::class);
        $this->call(CameraSeeder::class);
    }
}
