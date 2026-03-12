<?php

namespace App\Shared\Application\Data;

final readonly class ObservabilityMetricsSnapshot
{
    /**
     * @param  list<MetricSeriesData>  $httpRequests
     * @param  list<MetricHistogramData>  $httpDurations
     * @param  list<MetricSeriesData>  $cacheHits
     * @param  list<MetricSeriesData>  $cacheMisses
     * @param  list<MetricSeriesData>  $integrationErrors
     * @param  list<MetricSeriesData>  $paymentReconciliationFailures
     * @param  list<MetricSeriesData>  $webhookVerificationFailures
     */
    public function __construct(
        public array $httpRequests,
        public array $httpDurations,
        public array $cacheHits,
        public array $cacheMisses,
        public array $integrationErrors,
        public array $paymentReconciliationFailures,
        public array $webhookVerificationFailures,
    ) {}
}
