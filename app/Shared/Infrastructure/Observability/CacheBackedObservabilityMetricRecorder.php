<?php

namespace App\Shared\Infrastructure\Observability;

use App\Shared\Application\Contracts\ObservabilityMetricRecorder;
use App\Shared\Application\Data\MetricHistogramData;
use App\Shared\Application\Data\MetricSeriesData;
use App\Shared\Application\Data\ObservabilityMetricsSnapshot;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

/**
 * @phpstan-type MetricLabels array<string, string>
 * @phpstan-type CounterEntry array{labels: MetricLabels, value: float}
 * @phpstan-type HistogramEntry array{labels: MetricLabels, count: int, sum: float, buckets: array<string, int>}
 * @phpstan-type MetricsState array{
 *     http_requests?: array<string, CounterEntry>,
 *     http_durations?: array<string, HistogramEntry>,
 *     cache_hits?: array<string, CounterEntry>,
 *     cache_misses?: array<string, CounterEntry>,
 *     integration_errors?: array<string, CounterEntry>,
 *     payment_reconciliation_failures?: array<string, CounterEntry>,
 *     webhook_verification_failures?: array<string, CounterEntry>
 * }
 */
final class CacheBackedObservabilityMetricRecorder implements ObservabilityMetricRecorder
{
    private const CACHE_KEY = 'ops:telemetry:metrics';

    public function __construct(
        private readonly CacheFactory $cacheFactory,
    ) {}

    #[\Override]
    public function recordHttpRequest(string $method, string $route, int $statusCode, float $durationSeconds): void
    {
        $labels = [
            'method' => strtoupper($method),
            'route' => $route,
            'status_code' => (string) $statusCode,
            'status_class' => $this->statusClass($statusCode),
        ];

        $this->mutate(function (array $state) use ($labels, $durationSeconds): array {
            /** @var MetricsState $state */
            $fingerprint = $this->fingerprint($labels);
            $httpRequests = $state['http_requests'] ?? [];
            $counter = $httpRequests[$fingerprint] ?? $this->newCounterEntry($labels);
            $counter['value'] = $counter['value'] + 1.0;
            $httpRequests[$fingerprint] = $counter;
            $state['http_requests'] = $httpRequests;

            $httpDurations = $state['http_durations'] ?? [];
            $histogram = $httpDurations[$fingerprint] ?? $this->newHistogramEntry($labels);
            $histogram['count']++;
            $histogram['sum'] += $durationSeconds;

            foreach ($this->httpDurationBuckets() as $bucket) {
                if ($durationSeconds <= $bucket) {
                    $histogram['buckets'][(string) $bucket] = ($histogram['buckets'][(string) $bucket] ?? 0) + 1;
                }
            }

            $histogram['buckets']['+Inf'] = ($histogram['buckets']['+Inf'] ?? 0) + 1;
            $httpDurations[$fingerprint] = $histogram;
            $state['http_durations'] = $httpDurations;

            return $state;
        });
    }

    #[\Override]
    public function recordCacheHit(string $domain, bool $tenantScoped): void
    {
        $this->recordSimpleCounter('cache_hits', [
            'domain' => $domain,
            'scope' => $tenantScoped ? 'tenant' : 'global',
        ]);
    }

    #[\Override]
    public function recordCacheMiss(string $domain, bool $tenantScoped): void
    {
        $this->recordSimpleCounter('cache_misses', [
            'domain' => $domain,
            'scope' => $tenantScoped ? 'tenant' : 'global',
        ]);
    }

    #[\Override]
    public function recordIntegrationError(string $provider, string $operation, string $errorType): void
    {
        $this->recordSimpleCounter('integration_errors', [
            'provider' => $provider,
            'operation' => $operation,
            'error_type' => $errorType,
        ]);
    }

    #[\Override]
    public function recordPaymentReconciliationFailure(string $provider): void
    {
        $this->recordSimpleCounter('payment_reconciliation_failures', [
            'provider' => $provider,
        ]);
    }

    #[\Override]
    public function recordWebhookVerificationFailure(string $provider): void
    {
        $this->recordSimpleCounter('webhook_verification_failures', [
            'provider' => $provider,
        ]);
    }

    #[\Override]
    public function snapshot(): ObservabilityMetricsSnapshot
    {
        $state = $this->state();

        return new ObservabilityMetricsSnapshot(
            httpRequests: $this->series($state['http_requests'] ?? []),
            httpDurations: $this->histograms($state['http_durations'] ?? []),
            cacheHits: $this->series($state['cache_hits'] ?? []),
            cacheMisses: $this->series($state['cache_misses'] ?? []),
            integrationErrors: $this->series($state['integration_errors'] ?? []),
            paymentReconciliationFailures: $this->series($state['payment_reconciliation_failures'] ?? []),
            webhookVerificationFailures: $this->series($state['webhook_verification_failures'] ?? []),
        );
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback
     */
    private function mutate(callable $callback): void
    {
        $store = $this->cacheFactory->store();
        /** @var MetricsState $nextState */
        $nextState = $callback($this->state());
        $store->forever(self::CACHE_KEY, $nextState);
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function recordSimpleCounter(string $bucket, array $labels): void
    {
        $this->mutate(function (array $state) use ($bucket, $labels): array {
            /** @var MetricsState $state */
            $fingerprint = $this->fingerprint($labels);
            $counterBucket = $this->counterBucket($state, $bucket);
            $counter = $counterBucket[$fingerprint] ?? $this->newCounterEntry($labels);
            $counter['value'] = $counter['value'] + 1.0;
            $counterBucket[$fingerprint] = $counter;
            $state[$bucket] = $counterBucket;

            return $state;
        });
    }

    /**
     * @return MetricsState
     */
    private function state(): array
    {
        $store = $this->cacheFactory->store();
        /** @var mixed $state */
        $state = $store->get(self::CACHE_KEY, []);

        return $this->normalizeState($state);
    }

    /**
     * @param  array<string, CounterEntry>  $items
     * @return list<MetricSeriesData>
     */
    private function series(array $items): array
    {
        $series = [];

        foreach ($items as $item) {
            $series[] = new MetricSeriesData(
                labels: $item['labels'],
                value: $item['value'],
            );
        }

        return $series;
    }

    /**
     * @param  array<string, HistogramEntry>  $items
     * @return list<MetricHistogramData>
     */
    private function histograms(array $items): array
    {
        $histograms = [];

        foreach ($items as $item) {
            $histograms[] = new MetricHistogramData(
                labels: $item['labels'],
                count: $item['count'],
                sum: $item['sum'],
                buckets: $item['buckets'],
            );
        }

        return $histograms;
    }

    /**
     * @param  MetricsState  $state
     * @return array<string, CounterEntry>
     */
    private function counterBucket(array $state, string $bucket): array
    {
        return match ($bucket) {
            'cache_hits' => $state['cache_hits'] ?? [],
            'cache_misses' => $state['cache_misses'] ?? [],
            'integration_errors' => $state['integration_errors'] ?? [],
            'payment_reconciliation_failures' => $state['payment_reconciliation_failures'] ?? [],
            'webhook_verification_failures' => $state['webhook_verification_failures'] ?? [],
            default => [],
        };
    }

    /**
     * @param  array<string, string>  $labels
     * @return CounterEntry
     */
    private function newCounterEntry(array $labels): array
    {
        return [
            'labels' => $labels,
            'value' => 0.0,
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return HistogramEntry
     */
    private function newHistogramEntry(array $labels): array
    {
        return [
            'labels' => $labels,
            'count' => 0,
            'sum' => 0.0,
            'buckets' => [],
        ];
    }

    /**
     * @return MetricsState
     */
    private function normalizeState(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        return [
            'http_requests' => $this->normalizeCounterBucket($state['http_requests'] ?? []),
            'http_durations' => $this->normalizeHistogramBucket($state['http_durations'] ?? []),
            'cache_hits' => $this->normalizeCounterBucket($state['cache_hits'] ?? []),
            'cache_misses' => $this->normalizeCounterBucket($state['cache_misses'] ?? []),
            'integration_errors' => $this->normalizeCounterBucket($state['integration_errors'] ?? []),
            'payment_reconciliation_failures' => $this->normalizeCounterBucket($state['payment_reconciliation_failures'] ?? []),
            'webhook_verification_failures' => $this->normalizeCounterBucket($state['webhook_verification_failures'] ?? []),
        ];
    }

    /**
     * @return array<string, CounterEntry>
     */
    private function normalizeCounterBucket(mixed $bucket): array
    {
        if (! is_array($bucket)) {
            return [];
        }

        $normalized = [];

        foreach ($bucket as $fingerprint => $entry) {
            if (! is_string($fingerprint) || ! is_array($entry)) {
                continue;
            }

            /** @var mixed $rawLabels */
            $rawLabels = $entry['labels'] ?? [];

            if (! is_array($rawLabels)) {
                $rawLabels = [];
            }

            $labels = $this->stringLabels($rawLabels);
            $value = $entry['value'] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $normalized[$fingerprint] = [
                'labels' => $labels,
                'value' => (float) $value,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, HistogramEntry>
     */
    private function normalizeHistogramBucket(mixed $bucket): array
    {
        if (! is_array($bucket)) {
            return [];
        }

        $normalized = [];

        foreach ($bucket as $fingerprint => $entry) {
            if (! is_string($fingerprint) || ! is_array($entry)) {
                continue;
            }

            $count = $entry['count'] ?? null;
            $sum = $entry['sum'] ?? null;
            $buckets = $entry['buckets'] ?? null;
            /** @var mixed $rawLabels */
            $rawLabels = $entry['labels'] ?? [];

            if (! is_numeric($count) || ! is_numeric($sum) || ! is_array($buckets) || ! is_array($rawLabels)) {
                continue;
            }

            $normalizedBuckets = [];

            foreach (array_keys($buckets) as $boundary) {
                /** @var mixed $value */
                $value = $buckets[$boundary];

                if (is_numeric($value)) {
                    $normalizedBuckets[(string) $boundary] = (int) $value;
                }
            }

            $normalized[$fingerprint] = [
                'labels' => $this->stringLabels($rawLabels),
                'count' => (int) $count,
                'sum' => (float) $sum,
                'buckets' => $normalizedBuckets,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<array-key, mixed>  $labels
     * @return array<string, string>
     */
    private function stringLabels(array $labels): array
    {
        $normalized = [];

        foreach (array_keys($labels) as $key) {
            /** @var mixed $value */
            $value = $labels[$key];

            if (is_scalar($value)) {
                $normalized[(string) $key] = (string) $value;
            }
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function fingerprint(array $labels): string
    {
        ksort($labels);

        return hash('sha256', json_encode($labels, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<float>
     */
    private function httpDurationBuckets(): array
    {
        $configured = config('operations.metrics.http_duration_buckets', [0.05, 0.1, 0.25, 0.5, 1, 2, 5]);

        if (! is_array($configured)) {
            return [0.05, 0.1, 0.25, 0.5, 1, 2, 5];
        }

        $buckets = [];

        foreach (array_keys($configured) as $key) {
            /** @var mixed $bucket */
            $bucket = $configured[$key];

            if (is_numeric($bucket)) {
                $buckets[] = (float) $bucket;
            }
        }

        sort($buckets);

        return $buckets === [] ? [0.05, 0.1, 0.25, 0.5, 1, 2, 5] : $buckets;
    }

    private function statusClass(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return '5xx';
        }

        if ($statusCode >= 400) {
            return '4xx';
        }

        if ($statusCode >= 300) {
            return '3xx';
        }

        if ($statusCode >= 200) {
            return '2xx';
        }

        return '1xx';
    }
}
