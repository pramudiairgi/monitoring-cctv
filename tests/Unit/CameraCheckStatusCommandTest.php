<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Camera;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class CameraCheckStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create([
            'name' => 'Traffic',
            'slug' => 'traffic',
        ]);
    }

    public function test_sets_online_when_stream_url_responds_200(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => Http::response('', 200),
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'offline',
            'category_id' => $this->category->id,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $camera->refresh();
        $this->assertEquals('online', $camera->status);
    }

    public function test_sets_offline_when_stream_url_fails(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => Http::response('', 500),
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'online',
            'category_id' => $this->category->id,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $camera->refresh();
        $this->assertEquals('offline', $camera->status);
    }

    public function test_sets_offline_when_connection_times_out(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'online',
            'category_id' => $this->category->id,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $camera->refresh();
        $this->assertEquals('offline', $camera->status);
    }

    public function test_does_not_update_when_status_unchanged(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => Http::response('', 200),
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'online',
            'category_id' => $this->category->id,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $camera->refresh();
        $this->assertEquals('online', $camera->status);
    }

    public function test_checks_all_cameras(): void
    {
        Http::fake([
            'https://example.com/stream1.m3u8' => Http::response('', 200),
            'https://example.com/stream2.m3u8' => Http::response('', 404),
            'https://example.com/stream3.m3u8' => Http::response('', 200),
        ]);

        Camera::factory()->create(['stream_url' => 'https://example.com/stream1.m3u8', 'status' => 'offline', 'category_id' => $this->category->id]);
        Camera::factory()->create(['stream_url' => 'https://example.com/stream2.m3u8', 'status' => 'online', 'category_id' => $this->category->id]);
        Camera::factory()->create(['stream_url' => 'https://example.com/stream3.m3u8', 'status' => 'offline', 'category_id' => $this->category->id]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $this->assertEquals('online', Camera::find(1)?->status);
        $this->assertEquals('offline', Camera::find(2)?->status);
        $this->assertEquals('online', Camera::find(3)?->status);
    }
}
