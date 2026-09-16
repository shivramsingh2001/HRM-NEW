<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use Tests\TestCase;

/**
 * Tier 2 / T2-D + T2-B — public API envelope, key auth, scopes, idempotency,
 * and the guarantee that the legacy /api/user/* surface is untouched.
 */
class ApiV1Test extends TestCase
{
    private function client(array $scopes = ['attendance:read']): array
    {
        $tenantId = (int) (\DB::table('tenants')->value('id') ?: 1);

        return ApiClient::issue($tenantId, 'phpunit ' . uniqid(), $scopes);
    }

    public function test_ping_returns_the_envelope(): void
    {
        $res = $this->getJson('/api/v1/ping');

        $res->assertOk()
            ->assertJsonStructure(['data' => ['status', 'version'], 'meta' => ['request_id']]);
        $this->assertSame('v1', $res->json('data.version'));
        $this->assertNotEmpty($res->headers->get('X-Request-Id'));
    }

    public function test_missing_key_is_401_in_the_envelope(): void
    {
        $this->getJson('/api/v1/attendance')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonStructure(['error' => ['code', 'message', 'details'], 'meta' => ['request_id']]);
    }

    public function test_valid_key_reads_attendance_paginated(): void
    {
        ['secret' => $secret] = $this->client(['attendance:read']);

        $this->withHeader('Authorization', "Bearer {$secret}")
            ->getJson('/api/v1/attendance?per_page=5')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['request_id', 'pagination' => ['total', 'per_page', 'current_page', 'last_page']],
            ]);
    }

    public function test_scope_is_enforced_and_the_failure_is_idempotently_replayed(): void
    {
        ['secret' => $secret] = $this->client(['attendance:read']); // no write scope

        $headers = ['Authorization' => "Bearer {$secret}", 'Idempotency-Key' => 'phpunit-' . uniqid()];
        $body = ['user_id' => 1, 'actor_user_id' => 1, 'date' => now()->subDay()->toDateString(), 'status' => 'present'];

        $first = $this->withHeaders($headers)->postJson('/api/v1/attendance/mark', $body);
        $first->assertStatus(403)->assertJsonPath('error.code', 'insufficient_scope');

        $second = $this->withHeaders($headers)->postJson('/api/v1/attendance/mark', $body);
        $second->assertStatus(403);
        $this->assertSame('true', $second->headers->get('Idempotent-Replayed'));
    }

    public function test_legacy_mobile_api_shape_is_unchanged(): void
    {
        // No JWT -> the legacy handler's bare {message} 401, NOT the v1 envelope.
        $res = $this->getJson('/api/user/attendance/today');

        $res->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertNull($res->json('error'));
        $this->assertNull($res->json('meta'));
    }
}
