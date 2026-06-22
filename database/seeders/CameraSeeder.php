<?php

namespace Database\Seeders;

use App\Models\Camera;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CameraSeeder extends Seeder
{
    public function run(): void
    {
        $cameras = [
            [
                'name' => '[PATROLI 1]',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/456894804730895764350561.m3u8',
                'category_slug' => 'traffic',
                'status' => 'online',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 2',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/704266912873134926588108.m3u8',
                'category_slug' => 'traffic',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'PATROLI 3',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/269243784546563313118434.m3u8',
                'category_slug' => 'traffic',
                'status' => 'online',
                'order' => 2,
            ],
        ];

        foreach ($cameras as $cam) {
            $category = Category::where('slug', $cam['category_slug'])->first();
            if ($category) {
                Camera::create([
                    'name' => $cam['name'],
                    'stream_url' => $cam['stream_url'],
                    'category_id' => $category->id,
                    'status' => $cam['status'],
                    'order' => $cam['order'],
                ]);
            }
        }
    }
}
