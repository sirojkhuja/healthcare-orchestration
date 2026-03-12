<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\Observability\Application\Contracts\JobAdministrationRepository;
use App\Modules\Observability\Application\Data\HealthCheckData;
use App\Modules\Observability\Application\Data\HealthReportData;
use App\Modules\Observability\Application\Data\LivenessData;
use App\Modules\Observability\Application\Data\VersionData;
use App\Shared\Application\Contracts\OutboxRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class HealthService
{
    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private readonly OutboxRepository $outboxRepository,
        private readonly JobAdministrationRepository $jobAdministrationRepository,
    ) {}

    public function health(): HealthReportData
    {
        $checkedAt = CarbonImmutable::now();
        $checks = $this->criticalChecks();
        $summary = $this->summary($checkedAt);
        $status = $this->criticalFailureExists($checks)
            ? 'failing'
            : ($summary['failed_jobs'] > 0 || $summary['outbox']['ready_count'] > 0 || $summary['outbox']['oldest_ready_age_seconds'] >= $this->warningThreshold()
                ? 'degraded'
                : 'healthy');

        if ($summary['failed_jobs'] > 0) {
            $checks[] = new HealthCheckData(
                key: 'failed_jobs',
                status: 'warn',
                message: 'Failed jobs are present.',
                details: ['count' => $summary['failed_jobs']],
            );
        }

        if ($summary['outbox']['ready_count'] > 0) {
            $checks[] = new HealthCheckData(
                key: 'outbox',
                status: 'warn',
                message: 'Outbox backlog is waiting for delivery.',
                details: $summary['outbox'],
            );
        }

        return new HealthReportData(
            status: $status,
            checkedAt: $checkedAt,
            checks: $checks,
            summary: $summary,
        );
    }

    public function live(): LivenessData
    {
        return new LivenessData(
            status: 'alive',
            service: config()->string('app.name'),
            version: config()->string('medflow.version'),
            checkedAt: CarbonImmutable::now(),
        );
    }

    public function readiness(): HealthReportData
    {
        $checkedAt = CarbonImmutable::now();
        $checks = $this->criticalChecks();

        return new HealthReportData(
            status: $this->criticalFailureExists($checks) ? 'not_ready' : 'ready',
            checkedAt: $checkedAt,
            checks: $checks,
        );
    }

    public function version(): VersionData
    {
        $gitSha = $this->optionalConfigString('operations.runtime.git_sha');

        return new VersionData(
            service: config()->string('app.name'),
            environment: config()->string('app.env'),
            version: config()->string('medflow.version'),
            phpVersion: PHP_VERSION,
            laravelVersion: app()->version(),
            modules: $this->modules(),
            gitSha: $gitSha,
        );
    }

    /**
     * @return list<HealthCheckData>
     */
    private function criticalChecks(): array
    {
        return [
            $this->databaseCheck(),
            $this->cacheCheck(),
            $this->queueCheck(),
            $this->kafkaConfigCheck(),
        ];
    }

    /**
     * @param  list<HealthCheckData>  $checks
     */
    private function criticalFailureExists(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check->status === 'fail') {
                return true;
            }
        }

        return false;
    }

    private function cacheCheck(): HealthCheckData
    {
        $store = $this->cacheFactory->store();
        $key = 'ops:health:'.Str::uuid()->toString();

        try {
            $store->put($key, 'ok', 10);
            $value = $store->get($key);
            $store->forget($key);

            if ($value !== 'ok') {
                return new HealthCheckData('cache', 'fail', 'Cache round trip failed.');
            }
        } catch (Throwable $throwable) {
            return new HealthCheckData('cache', 'fail', 'Cache store is not ready.', [
                'error' => $throwable->getMessage(),
            ]);
        }

        return new HealthCheckData('cache', 'pass', 'Cache store is reachable.');
    }

    private function databaseCheck(): HealthCheckData
    {
        try {
            DB::select('SELECT 1');
        } catch (Throwable $throwable) {
            return new HealthCheckData('database', 'fail', 'Database connection failed.', [
                'error' => $throwable->getMessage(),
            ]);
        }

        return new HealthCheckData('database', 'pass', 'Database connection is healthy.');
    }

    private function kafkaConfigCheck(): HealthCheckData
    {
        $brokers = trim(config()->string('medflow.kafka.brokers', ''));

        if ($brokers === '') {
            return new HealthCheckData('kafka', 'fail', 'Kafka brokers are not configured.');
        }

        return new HealthCheckData('kafka', 'pass', 'Kafka brokers are configured.', [
            'brokers' => array_values(array_filter(array_map('trim', explode(',', $brokers)))),
        ]);
    }

    private function queueCheck(): HealthCheckData
    {
        try {
            $connection = config()->string('queue.default');

            if ($connection === 'database') {
                DB::table('jobs')->limit(1)->count();
                DB::table('failed_jobs')->limit(1)->count();
            }
        } catch (Throwable $throwable) {
            return new HealthCheckData('queue', 'fail', 'Queue backend is not ready.', [
                'error' => $throwable->getMessage(),
            ]);
        }

        return new HealthCheckData('queue', 'pass', 'Queue backend is reachable.', [
            'connection' => config()->string('queue.default'),
        ]);
    }

    /**
     * @return array{failed_jobs: int, outbox: array{ready_count: int, oldest_ready_age_seconds: int}}
     */
    private function summary(CarbonImmutable $checkedAt): array
    {
        $lag = $this->outboxRepository->lagMetrics($checkedAt);
        $jobs = $this->jobAdministrationRepository->summary();

        return [
            'failed_jobs' => $jobs['failed_jobs'],
            'outbox' => [
                'ready_count' => $lag->readyCount,
                'oldest_ready_age_seconds' => $lag->oldestReadyAgeSeconds,
            ],
        ];
    }

    private function warningThreshold(): int
    {
        return config()->integer('operations.health.outbox_warning_age_seconds', 60);
    }

    private function optionalConfigString(string $key): ?string
    {
        /** @var mixed $value */
        $value = config($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        $configured = config('medflow.modules', []);

        if (! is_array($configured)) {
            return [];
        }

        $modules = array_values(array_filter(
            $configured,
            static fn (mixed $module): bool => is_string($module),
        ));

        /** @var list<string> $modules */

        return $modules;
    }
}
