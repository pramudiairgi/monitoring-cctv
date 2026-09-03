<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelemetryJob;
use App\Models\Camera;
use App\Models\Category;
use App\Models\StreamTelemetry;
use App\Rules\PublicHttpUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityGapsTest extends TestCase
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

    // --- SSRF rule: private/reserved literal IPs rejected ---

    #[DataProvider('privateUrlProvider')]
    public function test_public_http_url_rule_rejects_private_and_reserved_ips(string $url): void
    {
        $this->assertFalse(PublicHttpUrl::isAllowed($url), "Expected rejection: {$url}");

        $validator = validator(
            ['stream_url' => $url],
            ['stream_url' => ['required', 'url', new PublicHttpUrl()]]
        );
        $this->assertTrue($validator->fails(), "Expected validation failure: {$url}");
    }

    public static function privateUrlProvider(): array
    {
        return [
            'loopback v4' => ['http://127.0.0.1/stream.m3u8'],
            'loopback with port' => ['http://127.0.0.1:8080/stream.m3u8'],
            '10/8 private' => ['http://10.0.0.5/stream.m3u8'],
            '172.16/12 private' => ['http://172.16.0.1/stream.m3u8'],
            '192.168/16 private' => ['https://192.168.1.1/stream.m3u8'],
            'link-local (cloud metadata range)' => ['http://169.254.169.254/latest/meta-data/'],
            'non-http scheme' => ['ftp://93.184.216.34/stream.m3u8'],
            'missing scheme' => ['93.184.216.34/stream.m3u8'],
        ];
    }

    public function test_public_http_url_rule_rejects_unresolvable_host(): void
    {
        $url = 'https://this-host-definitely-does-not-exist-xyz123.invalid/stream.m3u8';

        $this->assertFalse(PublicHttpUrl::isAllowed($url));

        $validator = validator(
            ['stream_url' => $url],
            ['stream_url' => ['required', 'url', new PublicHttpUrl()]]
        );
        $this->assertTrue($validator->fails());
    }

    public function test_public_http_url_rule_accepts_public_ip_url(): void
    {
        // 93.184.216.34 is a public documentation IP — no DNS lookup needed.
        $this->assertTrue(PublicHttpUrl::isAllowed('https://93.184.216.34/stream.m3u8'));

        $validator = validator(
            ['stream_url' => 'https://93.184.216.34/stream.m3u8'],
            ['stream_url' => ['required', 'url', new PublicHttpUrl()]]
        );
        $this->assertTrue($validator->passes());
    }

    // --- Command: unsafe URLs skipped (never fetched), camera marked offline ---

    public function test_command_skips_private_ip_url_and_marks_offline(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $camera = Camera::factory()->create([
            'stream_url' => 'http://127.0.0.1:8080/stream.m3u8',
            'adaptive_url' => null,
            'status' => 'online',
            'category_id' => $this->category->id,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $this->assertEquals('offline', $camera->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_command_still_checks_public_url_normally(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $camera = Camera::factory()->create([
            'stream_url' => 'https://93.184.216.34/stream.m3u8',
            'adaptive_url' => null,
            'status' => 'offline',
            'category_id' => $this->category->id,
        ]);

        $this->artisan('cameras:check-status')->assertSuccessful();

        $this->assertEquals('online', $camera->refresh()->status);
        Http::assertSentCount(1);
    }

    // --- Telemetry: batch size cap ---

    public function test_telemetry_rejects_batch_over_100_events(): void
    {
        Queue::fake();

        $events = [];
        for ($i = 0; $i < 101; $i++) {
            $events[] = ['event_type' => 'heartbeat', 'latency_ms' => $i];
        }

        $this->postJson('/api/telemetry', $events)->assertStatus(422);

        Queue::assertNothingPushed();
        $this->assertEquals(0, StreamTelemetry::count());
    }

    public function test_telemetry_accepts_normal_batch_up_to_20_events(): void
    {
        Queue::fake();

        $events = [];
        for ($i = 0; $i < 20; $i++) {
            $events[] = ['event_type' => 'heartbeat', 'latency_ms' => $i];
        }

        $this->postJson('/api/telemetry', $events)->assertNoContent();

        Queue::assertPushed(ProcessTelemetryJob::class, 1);
    }
}
