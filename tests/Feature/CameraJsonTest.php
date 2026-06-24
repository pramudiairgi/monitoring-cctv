<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\File;

class CameraJsonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $path = storage_path('app/public/cameras.json');
        File::put($path, json_encode([
            'cameras' => [
                ['id' => 1, 'name' => 'Camera 1', 'stream_url' => 'https://example.com/stream.m3u8', 'adaptive_url' => null, 'category' => 'traffic', 'status' => 'online'],
                ['id' => 2, 'name' => 'Camera 2', 'stream_url' => 'https://example.com/stream2.m3u8', 'adaptive_url' => null, 'category' => 'parking', 'status' => 'offline'],
            ],
            'categories' => [
                ['value' => 'traffic', 'label' => 'Traffic'],
                ['value' => 'parking', 'label' => 'Parking'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        $path = storage_path('app/public/cameras.json');
        if (File::exists($path)) {
            File::delete($path);
        }
        parent::tearDown();
    }

    public function test_returns_cameras_json(): void
    {
        $response = $this->get('/cameras.json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'cameras' => [
                '*' => ['id', 'name', 'stream_url', 'category', 'status'],
            ],
            'categories',
        ]);
    }

    public function test_returns_404_when_no_cameras_json(): void
    {
        File::delete(storage_path('app/public/cameras.json'));

        $response = $this->get('/cameras.json');

        $response->assertStatus(404);
    }

    public function test_returns_no_cache_header(): void
    {
        $response = $this->get('/cameras.json');

        $response->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    public function test_returns_all_cameras(): void
    {
        $response = $this->get('/cameras.json');

        $response->assertJsonCount(2, 'cameras');
        $response->assertJsonCount(2, 'categories');
    }

    public function test_returns_online_camera_data(): void
    {
        $response = $this->get('/cameras.json');

        $response->assertJsonPath('cameras.0.status', 'online');
        $response->assertJsonPath('cameras.1.status', 'offline');
    }
}
