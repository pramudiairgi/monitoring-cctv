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
            ['name' => 'Patroli', 'slug' => 'patroli'],
            ['name' => 'Public Facility', 'slug' => 'public_facility']
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
