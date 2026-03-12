<?php

namespace App\Modules\Observability\Application\Contracts;

use App\Modules\Observability\Application\Data\FailedJobData;

interface JobAdministrationRepository
{
    /**
     * @return array{ready_jobs: int, reserved_jobs: int, failed_jobs: int, pending_batches: int}
     */
    public function summary(): array;

    /**
     * @return list<FailedJobData>
     */
    public function listFailed(?string $queue, int $limit): array;

    public function retryFailedJob(string $jobId): ?FailedJobData;
}
