<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class FailedJobData
{
    public function __construct(
        public string $jobId,
        public string $uuid,
        public string $connection,
        public string $queue,
        public string $displayName,
        public string $errorSummary,
        public CarbonImmutable $failedAt,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->jobId,
            'uuid' => $this->uuid,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'display_name' => $this->displayName,
            'error_summary' => $this->errorSummary,
            'failed_at' => $this->failedAt->toIso8601String(),
        ];
    }
}
