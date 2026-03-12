<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/../Feature/Modules/Observability/ObservabilityTestSupport.php';

uses(TestCase::class, RefreshDatabase::class);

it('keeps the documented operational baseline endpoints within reviewed latency thresholds', function (): void {
    $manager = User::factory()->create([
        'email' => 'ops.performance.manager@example.test',
        'password' => 'secret-password',
    ]);

    $token = observabilityIssueBearerToken($this, 'ops.performance.manager@example.test');
    $tenantId = observabilityCreateTenant($this, $token, 'Performance Baseline Tenant')->json('data.id');
    observabilityGrantPermissions($manager, $tenantId, ['admin.view']);

    /** @var array<string, float> $thresholds */
    $thresholds = config('governance.performance.baseline_thresholds_ms', []);
    $iterations = (int) config('governance.performance.iterations', 8);

    $pingBaseline = measurePerformanceBaseline(
        fn () => $this->getJson('/api/v1/ping')->assertOk(),
        $iterations,
    );
    $metricsBaseline = measurePerformanceBaseline(
        fn () => $this->withToken($token)
            ->withHeader('X-Tenant-Id', $tenantId)
            ->get('/api/v1/metrics')
            ->assertOk(),
        $iterations,
    );
    $internalMetricsBaseline = measurePerformanceBaseline(
        fn () => $this->withHeader('X-Prometheus-Scrape-Key', 'testing-prometheus-scrape')
            ->get('/internal/metrics')
            ->assertOk(),
        $iterations,
    );

    expect($pingBaseline['average_ms'])->toBeLessThanOrEqual($thresholds['public_ping_average'] ?? 75.0);
    expect($metricsBaseline['average_ms'])->toBeLessThanOrEqual($thresholds['authenticated_metrics_average'] ?? 350.0);
    expect($internalMetricsBaseline['average_ms'])->toBeLessThanOrEqual($thresholds['internal_metrics_average'] ?? 200.0);
});

/**
 * @param  Closure(): TestResponse  $request
 * @return array{average_ms: float, max_ms: float}
 */
function measurePerformanceBaseline(Closure $request, int $iterations): array
{
    $durations = [];
    $runCount = max(1, $iterations);

    for ($index = 0; $index < $runCount; $index++) {
        $start = hrtime(true);
        $request();
        $durations[] = (hrtime(true) - $start) / 1_000_000;
    }

    return [
        'average_ms' => array_sum($durations) / count($durations),
        'max_ms' => max($durations),
    ];
}
