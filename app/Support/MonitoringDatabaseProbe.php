<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class MonitoringDatabaseProbe
{
    /**
     * @return array{status: 'ok'|'critical', latency_ms: int|null}
     */
    public function check(): array
    {
        $startedAt = hrtime(true);

        try {
            DB::connection()->selectOne('select 1');
        } catch (Throwable) {
            return [
                'status' => 'critical',
                'latency_ms' => null,
            ];
        }

        return [
            'status' => 'ok',
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }
}
