<?php

namespace Database\Seeders;

use App\Models\Camera;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CameraSeeder extends Seeder
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

        $cameras = [
            [
                'name' => 'PTZ 1 Pahlawan',
                'stream_url' => 'https://livepantau.semarangkota.go.id/41bedb9a-b93f-4f7d-b1a4-b6d0bc166c7d/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'Pahlawan 180',
                'stream_url' => 'https://livepantau.semarangkota.go.id/5a9b5e8f-3336-4a0a-8fe0-6a7e48c43c1f/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'PTZ 2 Pahlawan',
                'stream_url' => 'https://livepantau.semarangkota.go.id/d7fa15ea-df64-4865-a24e-32a333b17207/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'Tugumuda 3',
                'stream_url' => 'https://livepantau.semarangkota.go.id/9ea9723a-1384-42dd-9ca7-0b593a4c000b/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'lalin',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'PINTU MASUK BALAIKOTA 1',
                'stream_url' => 'https://livepantau.semarangkota.go.id/fad816ff-1bc4-44ab-bc4e-82ccefcb9d71/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'PINTU MASUK BALAIKOTA 2',
                'stream_url' => 'https://livepantau.semarangkota.go.id/55cc5559-12b8-4c7e-9666-d0cd4f7a57ec/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'PINTU MASUK BALAIKOTA 3',
                'stream_url' => 'https://livepantau.semarangkota.go.id/0b6ca2de-6740-49c7-b763-ad29e476b544/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'PINTU KELUAR BALAIKOTA 1',
                'stream_url' => 'https://livepantau.semarangkota.go.id/cd0524ff-48fa-4774-a1e6-fd61959283a3/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'PINTU BELAKANG BALAIKOTA 3',
                'stream_url' => 'https://livepantau.semarangkota.go.id/1044173d-f9d0-4e73-b782-3a48456cc967/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'PINTU BELAKANG BALAIKOTA 2',
                'stream_url' => 'https://livepantau.semarangkota.go.id/25851800-86b6-442d-b886-2b25266ed5bb/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'TUGUMUDA 3-2',
                'stream_url' => 'https://livepantau.semarangkota.go.id/cad1b0a8-696f-419b-9da4-cea853304ce7/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'lalin',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'DPU PAHLAWAN',
                'stream_url' => 'https://livepantau.semarangkota.go.id/c954ee49-2de6-4e7e-be98-8e17c659f9a9/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'lalin',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'TPS BALAIKOTA',
                'stream_url' => 'https://livepantau.semarangkota.go.id/81d01d2c-304e-4f66-898f-22d86592aa87/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => null,
                'category_slug' => 'kantor-pemerintahan',
                'status' => 'online',
                'order' => 1,
            ],
        ];

        foreach ($cameras as $cam) {
            $category = Category::where('slug', $cam['category_slug'])->first();
            if (! $category) {
                continue;
            }

            Camera::updateOrCreate(
                ['name' => $cam['name']],
                [
                    'stream_url' => $cam['stream_url'],
                    'adaptive_url' => $cam['adaptive_url'],
                    'target_url' => $cam['target_url'],
                    'category_id' => $category->id,
                    'status' => $cam['status'],
                    'order' => $cam['order'],
                ]
            );
        }
    }
}
