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
            ['name' => 'Polsek', 'slug' => 'polsek'],
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
            // Standby catalogue (currently 404/offline, reused when patrol
            // goes live). status/order/target_url are create-only defaults
            // below and are never overwritten on re-seed.
            [
                'name' => 'PATROLI 1',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/305273463144370038881079.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/305273463144370038881079_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 2',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/456894804730895764350561.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/456894804730895764350561_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 3',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/704266912873134926588108.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/704266912873134926588108_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 4',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/316070009420151011926115.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/316070009420151011926115_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 5',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/269243784546563313118434.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/269243784546563313118434_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 6',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/855547824790277339148013.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/855547824790277339148013_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 7',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/504459775951506263441065.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/504459775951506263441065_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 8',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/698005882731233773189510.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/698005882731233773189510_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 9',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/718945451280463289963515.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/718945451280463289963515_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 10',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/273425931127851001994981.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/273425931127851001994981_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 11',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/836113559784315851701616.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/836113559784315851701616_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 12',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/457700364477230783854749.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/457700364477230783854749_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'Polsek Pedurungan',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/310042472124215705543913.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/310042472124215705543913_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'polsek',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'Polsek Semarang Tengah',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/923691955514471113115883.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/923691955514471113115883_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'polsek',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'Polsek Semarang Utara',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/856480786231112092706226.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/856480786231112092706226_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'polsek',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'Polsek Banyumanik',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/482390122098687704626744.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/482390122098687704626744_adaptive.m3u8',
                'target_url' => null,
                'category_slug' => 'polsek',
                'status' => 'offline',
                'order' => 0,
            ],
        ];

        foreach ($cameras as $cam) {
            $category = Category::where('slug', $cam['category_slug'])->first();
            if (! $category) {
                continue;
            }

            // Create-only defaults: status / order / target_url must never be
            // overwritten on re-deploy, or live health-check state flips back.
            $camera = Camera::firstOrCreate(
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

            // On existing rows only refresh catalogue wiring (URLs, category).
            if (! $camera->wasRecentlyCreated) {
                $camera->fill([
                    'stream_url' => $cam['stream_url'],
                    'adaptive_url' => $cam['adaptive_url'],
                    'category_id' => $category->id,
                ]);

                if ($camera->isDirty()) {
                    $camera->save();
                }
            }
        }

        // Prune retired catalogue entries left by earlier seeders. The live
        // list above is authoritative: anything not in it is legacy.
        $liveNames = collect($cameras)->pluck('name')->all();
        Camera::whereNotIn('name', $liveNames)->delete();

        Category::whereIn('slug', ['traffic', 'drone', 'public_facility'])->delete();
    }
}
