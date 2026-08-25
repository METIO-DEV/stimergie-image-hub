<?php

namespace App\Http\Controllers;

use App\Support\MonitoringDatabaseProbe;
use App\Support\MonitoringQueueProbe;
use Illuminate\Http\JsonResponse;

class MonitoringController extends Controller
{
    public function __construct(
        private readonly MonitoringDatabaseProbe $databaseProbe,
        private readonly MonitoringQueueProbe $queueProbe,
    ) {}

    public function ready(): JsonResponse
    {
        $database = $this->databaseProbe->check();
        $isReady = $database['status'] === 'ok';

        return $this->monitoringResponse([
            'status' => $isReady ? 'ok' : 'critical',
        ], $isReady ? 200 : 503);
    }

    public function ops(): JsonResponse
    {
        $database = $this->databaseProbe->check();
        $checks = [
            'database' => $database,
        ];

        $queueChecks = $this->queueProbe->check();

        if ($queueChecks !== null) {
            $checks = [...$checks, ...$queueChecks];
        }

        $status = $this->overallStatus($database, $queueChecks);
        $snapshot = [
            'schema_version' => 1,
            'service' => config('monitoring.service'),
            'generated_at' => now()->utc()->toIso8601String(),
            'status' => $status,
            'checks' => $checks,
        ];

        $release = config('monitoring.release');

        if (is_string($release) && $release !== '') {
            $snapshot['release'] = ['version' => $release];
        }

        return $this->monitoringResponse($snapshot);
    }

    /**
     * @param  array{status: 'ok'|'critical', latency_ms: int|null}  $database
     * @param  array{queues: array{status: 'ok'|'unavailable', queues: list<array{name: string, pending: int, oldest_job_age_s: int|null}>}, failed_jobs: array{status: 'ok'|'degraded'|'unavailable', count_last_5m: int|null}}|null  $queueChecks
     */
    private function overallStatus(array $database, ?array $queueChecks): string
    {
        if ($database['status'] === 'critical') {
            return 'critical';
        }

        if ($queueChecks === null) {
            return 'ok';
        }

        return $queueChecks['queues']['status'] === 'ok'
            && $queueChecks['failed_jobs']['status'] === 'ok'
            ? 'ok'
            : 'degraded';
    }

    private function monitoringResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Cache-Control', 'no-store');
    }
}
