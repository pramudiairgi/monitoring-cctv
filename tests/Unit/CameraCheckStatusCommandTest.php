<?php

namespace Tests\Unit;

use App\Models\Camera;
use App\Models\Category;
use App\Models\Setting;
use App\Models\PatrolLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

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

    public function test_retains_online_on_first_probe_failure(): void
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
        $this->assertEquals('online', $camera->status);
        $this->assertEquals(1, Cache::get("camera-check-fails:{$camera->id}"));
    }

    public function test_flips_offline_on_second_consecutive_failure(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'online',
            'category_id' => $this->category->id,
        ]);
        Cache::put("camera-check-fails:{$camera->id}", 1, now()->addMinutes(10));

        $this->artisan('cameras:check-status')->assertSuccessful();

        $camera->refresh();
        $this->assertEquals('offline', $camera->status);
    }

    public function test_counts_custom_priority_slug_from_settings(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => Http::response('', 200),
        ]);

        Setting::set('playback_priority_category', 'live-patroli');
        $category = Category::factory()->create([
            'name' => 'Live Patroli',
            'slug' => 'live-patroli',
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'offline',
            'category_id' => $category->id,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $this->assertEquals('online', $camera->fresh()->status);
        $this->assertEquals(
            1,
            PatrolLog::latest('checked_at')->first()->patrol_online_count
        );
    }

    public function test_resets_failure_counter_on_success(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => Http::response('', 200),
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'online',
            'category_id' => $this->category->id,
        ]);
        Cache::put("camera-check-fails:{$camera->id}", 1, now()->addMinutes(10));

        $this->artisan('cameras:check-status')->assertSuccessful();

        $camera->refresh();
        $this->assertEquals('online', $camera->status);
        $this->assertNull(Cache::get("camera-check-fails:{$camera->id}"));
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

        $camera1 = Camera::factory()->create(['stream_url' => 'https://example.com/stream1.m3u8', 'status' => 'offline', 'category_id' => $this->category->id]);
        $camera2 = Camera::factory()->create(['stream_url' => 'https://example.com/stream2.m3u8', 'status' => 'online', 'category_id' => $this->category->id]);
        $camera3 = Camera::factory()->create(['stream_url' => 'https://example.com/stream3.m3u8', 'status' => 'offline', 'category_id' => $this->category->id]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $this->assertEquals('online', $camera1->fresh()->status);
        // First consecutive failure is retained (debounce), not flipped.
        $this->assertEquals('online', $camera2->fresh()->status);
        $this->assertEquals('online', $camera3->fresh()->status);
    }

    public function test_resets_target_url_when_stream_url_changes(): void
    {
        Http::fake([
            'https://example.com/old-stream.m3u8' => Http::response('', 200),
            'https://example.com/new-stream.m3u8' => Http::response('', 200),
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/old-stream.m3u8',
            'adaptive_url' => null,
            'target_url' => 'https://example.com/old-stream.m3u8',
            'name' => 'Old Name',
            'status' => 'online',
            'category_id' => $this->category->id,
        ]);

        $camera->stream_url = 'https://example.com/new-stream.m3u8';
        $camera->save();

        $camera->refresh();
        $this->assertNull($camera->target_url);
    }

    public function test_preserves_target_url_when_unrelated_field_changes(): void
    {
        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'adaptive_url' => null,
            'target_url' => 'https://example.com/stream.m3u8',
            'name' => 'Old Name',
            'status' => 'online',
            'category_id' => $this->category->id,
        ]);

        $camera->name = 'New Name';
        $camera->save();

        $camera->refresh();
        $this->assertEquals('https://example.com/stream.m3u8', $camera->target_url);
    }

    public function test_pool_exception_does_not_crash_command(): void
    {
        Http::fake(function () {
            throw new \Exception('Unexpected pool error');
        });

        Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'offline',
            'category_id' => $this->category->id,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();
    }

    public function test_skips_cameras_in_maintenance_mode(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => Http::response('', 200),
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'offline',
            'category_id' => $this->category->id,
            'maintenance' => true,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $camera->refresh();
        // Status should NOT be changed to 'online' because maintenance mode is on.
        $this->assertEquals('offline', $camera->status);
        $this->assertTrue($camera->maintenance);
    }

    public function test_maintenance_camera_not_affected_by_stream_status(): void
    {
        Http::fake([
            'https://example.com/stream.m3u8' => Http::response('', 500),
        ]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://example.com/stream.m3u8',
            'status' => 'online',
            'category_id' => $this->category->id,
            'maintenance' => true,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $camera->refresh();
        // Status should NOT be changed to 'offline' because maintenance mode is on.
        $this->assertEquals('online', $camera->status);
        $this->assertTrue($camera->maintenance);
    }
}
