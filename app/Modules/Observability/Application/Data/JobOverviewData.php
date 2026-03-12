<?php

namespace App\Modules\Observability\Application\Data;

final readonly class JobOverviewData
{
    /**
     * @param  list<FailedJobData>  $items
     */
    public function __construct(
        public int $readyJobs,
        public int $reservedJobs,
        public int $failedJobs,
        public int $pendingBatches,
        public array $items,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => [
                'ready_jobs' => $this->readyJobs,
                'reserved_jobs' => $this->reservedJobs,
                'failed_jobs' => $this->failedJobs,
                'pending_batches' => $this->pendingBatches,
            ],
            'items' => array_map(
                static fn (FailedJobData $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
