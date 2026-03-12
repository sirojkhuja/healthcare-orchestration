<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\Observability\Application\Contracts\JobAdministrationRepository;
use App\Modules\Observability\Application\Contracts\KafkaAdministrationRepository;
use App\Shared\Application\Contracts\OutboxRepository;
use Carbon\CarbonImmutable;

final class MetricsService
{
    public function __construct(
        private readonly HealthService $healthService,
        private readonly JobAdministrationRepository $jobAdministrationRepository,
        private readonly KafkaAdministrationRepository $kafkaAdministrationRepository,
        private readonly OutboxRepository $outboxRepository,
    ) {}

    public function render(): string
    {
        $lines = [];
        $health = $this->healthService->health();
        $jobs = $this->jobAdministrationRepository->summary();
        $lag = $this->outboxRepository->lagMetrics(CarbonImmutable::now());
        $consumers = $this->kafkaAdministrationRepository->listLag();
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

        return implode("\n", $lines)."\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
