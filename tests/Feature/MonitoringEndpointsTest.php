<?php

namespace Tests\Feature;

use App\Support\MonitoringDatabaseProbe;
use App\Support\MonitoringQueueProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

class MonitoringEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'monitoring.token' => 'test-monitoring-token',
            'monitoring.release' => 'test-release',
            'monitoring.service' => 'stimergie-image-hub',
            'monitoring.queue_names' => ['default', 'sync'],
            'queue.default' => 'database',
        ]);
    }

    public function test_existing_liveness_endpoint_remains_available(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertJsonPath('status', 'up');
    }

    public function test_ready_requires_the_monitoring_token(): void
    {
        $this->getJson('/ready')->assertForbidden();
        $this->withHeader('X-Monitoring-Token', 'invalid')->getJson('/ready')->assertForbidden();
    }

    public function test_ready_reports_when_the_database_is_available(): void
    {
        $response = $this->monitoringRequest('/ready');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertNoStore($response);
    }

    public function test_ready_reports_service_unavailable_when_the_database_is_not_available(): void
    {
        $this->mock(MonitoringDatabaseProbe::class, function (MockInterface $mock): void {
            $mock->shouldReceive('check')->once()->andReturn([
                'status' => 'critical',
                'latency_ms' => null,
            ]);
        });

        $response = $this->monitoringRequest('/ready');

        $response
            ->assertStatus(503)
            ->assertJsonPath('status', 'critical');

        $this->assertNoStore($response);
    }

    public function test_ops_requires_the_monitoring_token(): void
    {
        $this->getJson('/ops')->assertForbidden();
        $this->withHeader('X-Monitoring-Token', 'invalid')->getJson('/ops')->assertForbidden();
    }

    public function test_ops_returns_a_snapshot_of_available_operational_signals(): void
    {
        $response = $this->monitoringRequest('/ops');

        $response
            ->assertOk()
            ->assertJsonPath('schema_version', 1)
            ->assertJsonPath('service', 'stimergie-image-hub')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('release.version', 'test-release')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.queues.status', 'ok')
            ->assertJsonPath('checks.queues.queues.0.name', 'default')
            ->assertJsonPath('checks.queues.queues.0.pending', 0)
            ->assertJsonPath('checks.failed_jobs.status', 'ok')
            ->assertJsonPath('checks.failed_jobs.count_last_5m', 0);

        $this->assertNoStore($response);
    }

    public function test_ops_keeps_a_readable_snapshot_when_the_database_is_down(): void
    {
        $this->mock(MonitoringDatabaseProbe::class, function (MockInterface $mock): void {
            $mock->shouldReceive('check')->once()->andReturn([
                'status' => 'critical',
                'latency_ms' => null,
            ]);
        });

        $this->mock(MonitoringQueueProbe::class, function (MockInterface $mock): void {
            $mock->shouldReceive('check')->once()->andReturn(null);
        });

        $response = $this->monitoringRequest('/ops');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'critical')
            ->assertJsonPath('checks.database.status', 'critical')
            ->assertJsonMissingPath('checks.workers')
            ->assertJsonMissingPath('checks.errors');

        $this->assertNoStore($response);
    }

    public function test_ops_reports_a_degraded_snapshot_when_recent_jobs_have_failed(): void
    {
        $this->mock(MonitoringDatabaseProbe::class, function (MockInterface $mock): void {
            $mock->shouldReceive('check')->once()->andReturn([
                'status' => 'ok',
                'latency_ms' => 12,
            ]);
        });

        $this->mock(MonitoringQueueProbe::class, function (MockInterface $mock): void {
            $mock->shouldReceive('check')->once()->andReturn([
                'queues' => [
                    'status' => 'ok',
                    'queues' => [],
                ],
                'failed_jobs' => [
                    'status' => 'degraded',
                    'count_last_5m' => 1,
                ],
            ]);
        });

        $this->monitoringRequest('/ops')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.failed_jobs.count_last_5m', 1);
    }

    private function monitoringRequest(string $uri)
    {
        return $this->withHeader('X-Monitoring-Token', 'test-monitoring-token')->getJson($uri);
    }

    private function assertNoStore(TestResponse $response): void
    {
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
