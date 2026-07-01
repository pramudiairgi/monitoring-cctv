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
                'name' => 'PATROLI 1',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/305273463144370038881079.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/305273463144370038881079_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/305273463144370038881079.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 2',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/456894804730895764350561.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/456894804730895764350561_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/456894804730895764350561.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 3',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/704266912873134926588108.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/704266912873134926588108_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/704266912873134926588108.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 4',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/316070009420151011926115.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/316070009420151011926115_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/316070009420151011926115.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 5',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/269243784546563313118434.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/269243784546563313118434_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/269243784546563313118434.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 6',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/855547824790277339148013.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/855547824790277339148013_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/855547824790277339148013.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 7',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/504459775951506263441065.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/504459775951506263441065_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/504459775951506263441065.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 8',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/698005882731233773189510.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/698005882731233773189510_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/698005882731233773189510.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 9',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/718945451280463289963515.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/718945451280463289963515_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/718945451280463289963515.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 10',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/273425931127851001994981.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/273425931127851001994981_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/273425931127851001994981.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 11',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/836113559784315851701616.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/836113559784315851701616_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/836113559784315851701616.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'PATROLI 12',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/457700364477230783854749.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/457700364477230783854749_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/457700364477230783854749.m3u8',
                'category_slug' => 'patroli',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'DPU Pahlawan',
                'stream_url' => 'https://livepantau.semarangkota.go.id/51e354eb-e854-45de-995b-78a4be3d2cf9/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => 'https://livepantau.semarangkota.go.id/51e354eb-e854-45de-995b-78a4be3d2cf9/video1_stream.m3u8',
                'category_slug' => 'traffic',
                'status' => 'online',
                'order' => 4,
            ],
            [
                'name' => 'Pahlawan PTZ 01',
                'stream_url' => 'https://livepantau.semarangkota.go.id/ce7f738e-7e30-4e64-888c-1bd22062f225/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => 'https://livepantau.semarangkota.go.id/ce7f738e-7e30-4e64-888c-1bd22062f225/video1_stream.m3u8',
                'category_slug' => 'public_facility',
                'status' => 'online',
                'order' => 2,
            ],
            [
                'name' => 'Pahlawan PTZ 02',
                'stream_url' => 'https://livepantau.semarangkota.go.id/a5e69eea-cd60-4cb1-a51a-209ec3704a71/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => 'https://livepantau.semarangkota.go.id/a5e69eea-cd60-4cb1-a51a-209ec3704a71/video1_stream.m3u8',
                'category_slug' => 'public_facility',
                'status' => 'online',
                'order' => 1,
            ],
            [
                'name' => 'Pahlawan 180',
                'stream_url' => 'https://livepantau.semarangkota.go.id/67e8ae0e-2ca1-4074-84fb-6a76503cd792/video1_stream.m3u8',
                'adaptive_url' => null,
                'target_url' => 'https://livepantau.semarangkota.go.id/67e8ae0e-2ca1-4074-84fb-6a76503cd792/video1_stream.m3u8',
                'category_slug' => 'public_facility',
                'status' => 'online',
                'order' => 3,
            ],
            [
                'name' => 'Polsek Pedurungan',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/310042472124215705543913.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/310042472124215705543913_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/310042472124215705543913.m3u8',
                'category_slug' => 'polsek',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'Polsek Semarang Tengah',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/923691955514471113115883.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/923691955514471113115883_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/923691955514471113115883.m3u8',
                'category_slug' => 'polsek',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'Polsek Semarang Utara',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/856480786231112092706226.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/856480786231112092706226_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/856480786231112092706226.m3u8',
                'category_slug' => 'polsek',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'Polsek Banyumanik',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/482390122098687704626744.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/482390122098687704626744_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/482390122098687704626744.m3u8',
                'category_slug' => 'polsek',
                'status' => 'offline',
                'order' => 0,
            ],
            [
                'name' => 'Drone',
                'stream_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/859687327474970365191961.m3u8',
                'adaptive_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/859687327474970365191961_adaptive.m3u8',
                'target_url' => 'https://media.pcctabessmg.xyz:5443/LiveApp/streams/859687327474970365191961.m3u8',
                'category_slug' => 'drone',
                'status' => 'offline',
                'order' => 0,
            ],
        ];

        foreach ($cameras as $cam) {
            $category = Category::where('slug', $cam['category_slug'])->first();
            if ($category) {
                Camera::create([
                    'name' => $cam['name'],
                    'stream_url' => $cam['stream_url'],
                    'adaptive_url' => $cam['adaptive_url'],
                    'target_url' => $cam['target_url'],
                    'category_id' => $category->id,
                    'status' => $cam['status'],
                    'order' => $cam['order'],
                ]);
            }
        }
    }
}
