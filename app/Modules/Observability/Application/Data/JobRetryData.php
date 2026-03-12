<?php

namespace App\Modules\Observability\Application\Data;

use Carbon\CarbonImmutable;

final readonly class JobRetryData
{
    public function __construct(
        public FailedJobData $job,
        public CarbonImmutable $retriedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'job' => $this->job->toArray(),
            'retried_at' => $this->retriedAt->toIso8601String(),
        ];
    }
}
