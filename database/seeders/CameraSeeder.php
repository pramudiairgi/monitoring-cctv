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
                'name' => 'CCTV - TUGUMUDA',
                'stream_url' => 'https://livepantau.semarangkota.go.id/41cc4b59-c86f-4307-939c-13a2026ff606/video1_stream.m3u8',
                'category_slug' => 'traffic',
                'status' => 'online',
                'order' => 0,
            ],
            [
                'name' => 'CCTV - SIMPANG WAGISAN TAMAN KASMARAN',
                'stream_url' => 'https://livepantau.semarangkota.go.id/e670cf31-b3f6-49a7-b197-cf4eaa45fc58/video1_stream.m3u8',
                'category_slug' => 'public_facility',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'CCTV - KALIGARANG',
                'stream_url' => 'https://livepantau.semarangkota.go.id/82226691-f696-41eb-8a74-8b3c9d78b595/video1_stream.m3u8',
                'category_slug' => 'traffic',
                'status' => 'online',
                'order' => 2,
            ],
            [
                'name' => 'CCTV Bandara',
                'stream_url' => 'https://example.com/stream/4.m3u8',
                'category_slug' => 'security',
                'status' => 'offline',
                'order' => 3,
            ],
            [
                'name' => 'CCTV Pelabuhan',
                'stream_url' => 'https://example.com/stream/5.m3u8',
                'category_slug' => 'public_facility',
                'status' => 'online',
                'order' => 4,
            ],
            [
                'name' => 'CCTV Pantai',
                'stream_url' => 'https://example.com/stream/6.m3u8',
                'category_slug' => 'environment',
                'status' => 'online',
                'order' => 5,
            ],
            [
                'name' => 'CCTV Tol Dalam Kota',
                'stream_url' => 'https://example.com/stream/7.m3u8',
                'category_slug' => 'traffic',
                'status' => 'offline',
                'order' => 6,
            ],
            [
                'name' => 'CCTV Stasiun',
                'stream_url' => 'https://example.com/stream/8.m3u8',
                'category_slug' => 'public_facility',
                'status' => 'online',
                'order' => 7,
            ],
            [
                'name' => 'CCTV Gempa Bumi',
                'stream_url' => 'https://example.com/stream/9.m3u8',
                'category_slug' => 'disaster',
                'status' => 'online',
                'order' => 8,
            ],
            [
                'name' => 'CCTV Pemukiman',
                'stream_url' => 'https://example.com/stream/10.m3u8',
                'category_slug' => 'security',
                'status' => 'online',
                'order' => 9,
            ],
            [
                'name' => 'CCTV Sungai',
                'stream_url' => 'https://example.com/stream/11.m3u8',
                'category_slug' => 'environment',
                'status' => 'online',
                'order' => 10,
            ],
            [
                'name' => 'CCTV Banjir',
                'stream_url' => 'https://example.com/stream/12.m3u8',
                'category_slug' => 'disaster',
                'status' => 'online',
                'order' => 11,
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
