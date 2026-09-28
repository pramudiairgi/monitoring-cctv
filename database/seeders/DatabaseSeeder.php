<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Live Patroli', 'slug' => 'patroli'],
            ['name' => 'Monitoring \u2013 Lalin', 'slug' => 'monitoring-lalin'],
            ['name' => 'Monitoring \u2013 Polsek', 'slug' => 'monitoring-polsek'],
            ['name' => 'Monitoring \u2013 Kantor', 'slug' => 'monitoring-kantor'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name']]
            );
        }

        $this->call(ShieldSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(CameraSeeder::class);
        $this->call(SettingsSeeder::class);
    }
}
