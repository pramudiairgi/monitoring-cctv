<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'playback_max_desktop' => '9',
            'playback_max_mobile_landscape' => '6',
            'playback_max_mobile_portrait' => '4',
            'playback_stagger_ms' => '350',
            'playback_priority_category' => 'patroli',
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
