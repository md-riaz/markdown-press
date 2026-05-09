<?php
namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health');
        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ok');
    }
}
