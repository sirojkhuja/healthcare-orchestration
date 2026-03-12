<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\ObservabilityMetricsSnapshot;

interface ObservabilityMetricRecorder
{
    public function recordHttpRequest(string $method, string $route, int $statusCode, float $durationSeconds): void;

    public function recordCacheHit(string $domain, bool $tenantScoped): void;

    public function recordCacheMiss(string $domain, bool $tenantScoped): void;

    public function recordIntegrationError(string $provider, string $operation, string $errorType): void;

    public function recordPaymentReconciliationFailure(string $provider): void;

    public function recordWebhookVerificationFailure(string $provider): void;

    public function snapshot(): ObservabilityMetricsSnapshot;
}
