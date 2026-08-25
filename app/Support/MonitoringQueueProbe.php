<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class MonitoringQueueProbe
{
    /**
     * @return array{
     *     queues: array{status: 'ok'|'unavailable', queues: list<array{name: string, pending: int, oldest_job_age_s: int|null}>},
     *     failed_jobs: array{status: 'ok'|'degraded'|'unavailable', count_last_5m: int|null}
     * }|null
     */
    public function check(): ?array
    {
        $connectionName = config('queue.default');
        $connection = config("queue.connections.{$connectionName}");
        $queueNames = config('monitoring.queue_names', []);

        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'database' || ! is_array($queueNames) || $queueNames === []) {
            return null;
        }

        $databaseConnection = $connection['connection'] ?? config('database.default');
        $table = $connection['table'] ?? 'jobs';
        $now = now();

        try {
            $rows = DB::connection($databaseConnection)
                ->table($table)
                ->select('queue')
                ->selectRaw('COUNT(*) as pending')
                ->selectRaw('MIN(created_at) as oldest_job_created_at')
                ->whereIn('queue', $queueNames)
                ->whereNull('reserved_at')
                ->where('available_at', '<=', $now->getTimestamp())
                ->groupBy('queue')
                ->get()
                ->keyBy('queue');

            $queues = collect($queueNames)
                ->map(function (string $queue) use ($rows, $now): array {
                    $row = $rows->get($queue);
                    $oldestJobCreatedAt = $row?->oldest_job_created_at;

                    return [
                        'name' => $queue,
                        'pending' => (int) ($row?->pending ?? 0),
                        'oldest_job_age_s' => $oldestJobCreatedAt === null
                            ? null
                            : max(0, $now->getTimestamp() - (int) $oldestJobCreatedAt),
                    ];
                })
                ->values()
                ->all();

            $failedJobsCount = DB::connection($databaseConnection)
                ->table('failed_jobs')
                ->where('connection', $connectionName)
                ->whereIn('queue', $queueNames)
                ->where('failed_at', '>=', $now->copy()->subMinutes(5))
                ->count();
        } catch (Throwable) {
            return [
                'queues' => [
                    'status' => 'unavailable',
                    'queues' => [],
                ],
                'failed_jobs' => [
                    'status' => 'unavailable',
                    'count_last_5m' => null,
                ],
            ];
        }

        return [
            'queues' => [
                'status' => 'ok',
                'queues' => $queues,
            ],
            'failed_jobs' => [
                'status' => $failedJobsCount === 0 ? 'ok' : 'degraded',
                'count_last_5m' => $failedJobsCount,
            ],
        ];
    }
}
