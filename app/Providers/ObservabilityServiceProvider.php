<?php

namespace App\Providers;

use App\Modules\Observability\Application\Contracts\FeatureFlagRepository;
use App\Modules\Observability\Application\Contracts\FeatureFlagResolver;
use App\Modules\Observability\Application\Contracts\JobAdministrationRepository;
use App\Modules\Observability\Application\Contracts\KafkaAdministrationRepository;
use App\Modules\Observability\Application\Contracts\RateLimitRepository;
use App\Modules\Observability\Application\Services\FeatureFlagService;
use App\Modules\Observability\Infrastructure\Persistence\DatabaseFeatureFlagRepository;
use App\Modules\Observability\Infrastructure\Persistence\DatabaseJobAdministrationRepository;
use App\Modules\Observability\Infrastructure\Persistence\DatabaseKafkaAdministrationRepository;
use App\Modules\Observability\Infrastructure\Persistence\DatabaseRateLimitRepository;
use App\Shared\Application\Contracts\ObservabilityMetricRecorder;
use App\Shared\Application\Contracts\RequestTracer;
use App\Shared\Application\Contracts\TraceContext;
use App\Shared\Infrastructure\Observability\CacheBackedObservabilityMetricRecorder;
use App\Shared\Infrastructure\Observability\Context\ContextBackedTraceContext;
use App\Shared\Infrastructure\Observability\Tracing\OpenTelemetryRequestTracer;
use Illuminate\Support\ServiceProvider;

final class ObservabilityServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(FeatureFlagRepository::class, DatabaseFeatureFlagRepository::class);
        $this->app->bind(JobAdministrationRepository::class, DatabaseJobAdministrationRepository::class);
        $this->app->bind(KafkaAdministrationRepository::class, DatabaseKafkaAdministrationRepository::class);
        $this->app->bind(RateLimitRepository::class, DatabaseRateLimitRepository::class);
        $this->app->scoped(FeatureFlagResolver::class, FeatureFlagService::class);
        $this->app->singleton(ObservabilityMetricRecorder::class, CacheBackedObservabilityMetricRecorder::class);
        $this->app->scoped(RequestTracer::class, OpenTelemetryRequestTracer::class);
        $this->app->scoped(TraceContext::class, ContextBackedTraceContext::class);
    }
}
