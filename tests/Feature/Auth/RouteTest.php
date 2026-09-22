<?php

namespace Tests\Feature\Auth;

use App\Models\Camera;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_telemetry_endpoint_is_public(): void
    {
        $camera = Camera::factory()->create();

        $response = $this->postJson('/api/telemetry', [
            ['event_type' => 'play', 'camera_id' => $camera->id],
        ]);
        $response->assertStatus(204);
    }

    public function test_ingest_returns_401_without_token(): void
    {
        $camera = Camera::factory()->create();

        $response = $this->postJson('/api/ingest', [
            ['event_type' => 'play', 'camera_id' => $camera->id],
        ]);
        $response->assertStatus(401);
    }

    public function test_ingest_returns_403_without_ability(): void
    {
        $user = User::factory()->create();
        $camera = Camera::factory()->create();
        Sanctum::actingAs($user, []);

        $response = $this->postJson('/api/ingest', [
            ['event_type' => 'play', 'camera_id' => $camera->id],
        ]);
        $response->assertStatus(403);
    }

    public function test_ingest_returns_204_with_valid_token_and_ability(): void
    {
        $user = User::factory()->create();
        $camera = Camera::factory()->create();
        Sanctum::actingAs($user, ['telemetry:submit']);

        $response = $this->postJson('/api/ingest', [
            ['event_type' => 'play', 'camera_id' => $camera->id],
        ]);
        $response->assertStatus(204);
    }

    public function test_cameras_json_remains_public(): void
    {
        $response = $this->getJson('/cameras.json');
        $response->assertStatus(200);
    }

    public function test_health_check_remains_public(): void
    {
        $response = $this->getJson('/up');
        $response->assertOk();
    }
}
