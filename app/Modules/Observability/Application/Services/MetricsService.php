<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\Observability\Application\Contracts\JobAdministrationRepository;
use App\Modules\Observability\Application\Contracts\KafkaAdministrationRepository;
use App\Shared\Application\Contracts\ObservabilityMetricRecorder;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Data\MetricSeriesData;
use Carbon\CarbonImmutable;

final class MetricsService
{
    public function __construct(
        private readonly HealthService $healthService,
        private readonly JobAdministrationRepository $jobAdministrationRepository,
        private readonly KafkaAdministrationRepository $kafkaAdministrationRepository,
        private readonly OutboxRepository $outboxRepository,
        private readonly ObservabilityMetricRecorder $metricRecorder,
    ) {}

    public function render(): string
    {
        $lines = [];
        $health = $this->healthService->health();
        $jobs = $this->jobAdministrationRepository->summary();
        $lag = $this->outboxRepository->lagMetrics(CarbonImmutable::now());
        $consumers = $this->kafkaAdministrationRepository->listLag();
        $telemetry = $this->metricRecorder->snapshot();
        $statusCode = match ($health->status) {
            'healthy' => 2,
            'degraded' => 1,
            default => 0,
        };

        $lines[] = '# TYPE medflow_app_info gauge';
        $lines[] = sprintf(
            'medflow_app_info{service="%s",version="%s",environment="%s"} 1',
            $this->escape(config()->string('app.name')),
            $this->escape(config()->string('medflow.version')),
            $this->escape(config()->string('app.env')),
        );
        $lines[] = '# TYPE medflow_health_status gauge';
        $lines[] = sprintf('medflow_health_status %d', $statusCode);
        $lines[] = '# TYPE medflow_outbox_ready_count gauge';
        $lines[] = sprintf('medflow_outbox_ready_count %d', $lag->readyCount);
        $lines[] = '# TYPE medflow_outbox_oldest_ready_age_seconds gauge';
        $lines[] = sprintf('medflow_outbox_oldest_ready_age_seconds %d', $lag->oldestReadyAgeSeconds);
        $lines[] = '# TYPE medflow_queue_ready_jobs gauge';
        $lines[] = sprintf('medflow_queue_ready_jobs %d', $jobs['ready_jobs']);
        $lines[] = '# TYPE medflow_queue_failed_jobs gauge';
        $lines[] = sprintf('medflow_queue_failed_jobs %d', $jobs['failed_jobs']);
        $lines[] = '# TYPE medflow_http_requests_total counter';
        $lines[] = '# TYPE medflow_http_request_duration_seconds histogram';
        $lines[] = '# TYPE medflow_cache_hits_total counter';
        $lines[] = '# TYPE medflow_cache_misses_total counter';
        $lines[] = '# TYPE medflow_cache_hit_ratio gauge';
        $lines[] = '# TYPE medflow_integration_errors_total counter';
        $lines[] = '# TYPE medflow_payment_reconciliation_failures_total counter';
        $lines[] = '# TYPE medflow_webhook_verification_failures_total counter';

        foreach ($consumers as $consumer) {
            $lines[] = sprintf(
                'medflow_kafka_consumer_processed_total{consumer_name="%s"} %d',
                $this->escape($consumer->consumerName),
                $consumer->processedTotal,
            );
            $lines[] = sprintf(
                'medflow_kafka_consumer_receipt_lag_seconds{consumer_name="%s"} %d',
                $this->escape($consumer->consumerName),
                $consumer->receiptLagSeconds,
            );
        }

        foreach ($telemetry->httpRequests as $series) {
            $lines[] = sprintf(
                'medflow_http_requests_total%s %s',
                $this->formatLabels($series->labels),
                $this->formatNumber($series->value),
            );
        }

        foreach ($telemetry->httpDurations as $histogram) {
            foreach ($histogram->buckets as $boundary => $count) {
                $lines[] = sprintf(
                    'medflow_http_request_duration_seconds_bucket%s %d',
                    $this->formatLabels($histogram->labels + ['le' => $boundary]),
                    $count,
                );
            }

            $lines[] = sprintf(
                'medflow_http_request_duration_seconds_sum%s %s',
                $this->formatLabels($histogram->labels),
                $this->formatNumber($histogram->sum),
            );
            $lines[] = sprintf(
                'medflow_http_request_duration_seconds_count%s %d',
                $this->formatLabels($histogram->labels),
                $histogram->count,
            );
        }

        foreach ($telemetry->cacheHits as $series) {
            $lines[] = sprintf(
                'medflow_cache_hits_total%s %s',
                $this->formatLabels($series->labels),
                $this->formatNumber($series->value),
            );
        }

        foreach ($telemetry->cacheMisses as $series) {
            $lines[] = sprintf(
                'medflow_cache_misses_total%s %s',
                $this->formatLabels($series->labels),
                $this->formatNumber($series->value),
            );
        }

        foreach ($this->cacheHitRatioSeries($telemetry->cacheHits, $telemetry->cacheMisses) as $series) {
            $lines[] = sprintf(
                'medflow_cache_hit_ratio%s %s',
                $this->formatLabels($series->labels),
                $this->formatNumber($series->value),
            );
        }

        foreach ($telemetry->integrationErrors as $series) {
            $lines[] = sprintf(
                'medflow_integration_errors_total%s %s',
                $this->formatLabels($series->labels),
                $this->formatNumber($series->value),
            );
        }

        foreach ($telemetry->paymentReconciliationFailures as $series) {
            $lines[] = sprintf(
                'medflow_payment_reconciliation_failures_total%s %s',
                $this->formatLabels($series->labels),
                $this->formatNumber($series->value),
            );
        }

        foreach ($telemetry->webhookVerificationFailures as $series) {
            $lines[] = sprintf(
                'medflow_webhook_verification_failures_total%s %s',
                $this->formatLabels($series->labels),
                $this->formatNumber($series->value),
            );
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<MetricSeriesData>  $hits
     * @param  list<MetricSeriesData>  $misses
     * @return list<MetricSeriesData>
     */
    private function cacheHitRatioSeries(array $hits, array $misses): array
    {
        /** @var array<string, array{labels: array<string, string>, hits: float, misses: float}> $totals */
        $totals = [];

        foreach ($hits as $series) {
            $fingerprint = $this->fingerprint($series->labels);
            $totals[$fingerprint] = [
                'labels' => $series->labels,
                'hits' => $series->value,
                'misses' => $totals[$fingerprint]['misses'] ?? 0.0,
            ];
        }

        foreach ($misses as $series) {
            $fingerprint = $this->fingerprint($series->labels);
            $totals[$fingerprint] = [
                'labels' => $series->labels,
                'hits' => $totals[$fingerprint]['hits'] ?? 0.0,
                'misses' => $series->value,
            ];
        }

        $ratios = [];

        foreach ($totals as $total) {
            $attempts = $total['hits'] + $total['misses'];

            if ($attempts <= 0) {
                continue;
            }

            $ratios[] = new MetricSeriesData(
                labels: $total['labels'],
                value: $total['hits'] / $attempts,
            );
        }

        return $ratios;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        ksort($labels);
        $parts = [];

        foreach ($labels as $key => $value) {
            $parts[] = sprintf('%s="%s"', $key, $this->escape($value));
        }

        return '{'.implode(',', $parts).'}';
    }

    private function formatNumber(float $value): string
    {
        if (fmod($value, 1.0) === 0.0) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function fingerprint(array $labels): string
    {
        ksort($labels);

        return hash('sha256', json_encode($labels, JSON_THROW_ON_ERROR));
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
