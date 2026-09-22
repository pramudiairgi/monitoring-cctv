<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function getValidCredentials(): array
    {
        return [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'test-device',
        ];
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/login', $this->getValidCredentials());

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type'])
            ->assertJson(['token_type' => 'Bearer']);

        $this->assertNotNull($user->tokens()->first());
    }

    public function test_login_returns_generic_error_for_invalid_credentials(): void
    {
        // Wrong email
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
            'device_name' => 'test-device',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');

        // Wrong password
        $user = User::factory()->create();
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
            'device_name' => 'test-device',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_throttles_brute_force(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
                'device_name' => 'test-device',
            ]);
        }

        $response = $this->postJson('/api/login', $this->getValidCredentials());
        $response->assertStatus(429);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device', ['telemetry:submit'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'logged out']);

        $this->assertEmpty($user->fresh()->tokens);
    }

    public function test_refresh_revokes_old_and_issues_new(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('old-device', ['telemetry:submit'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type'])
            ->assertJson(['token_type' => 'Bearer']);

        $newToken = $response->json('token');
        $this->assertNotEquals($oldToken, $newToken);

        // Old token should be revoked
        $this->assertNull($user->tokens()->where('id', $user->tokens()->first()->id)->where('token', $oldToken)->first());
    }

    public function test_refresh_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/refresh');
        $response->assertStatus(401);
    }

    public function test_logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/logout');
        $response->assertStatus(401);
    }
}
