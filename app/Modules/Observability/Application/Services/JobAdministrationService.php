<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Observability\Application\Contracts\JobAdministrationRepository;
use App\Modules\Observability\Application\Data\JobOverviewData;
use App\Modules\Observability\Application\Data\JobRetryData;
use App\Modules\Observability\Application\Data\JobSearchCriteria;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class JobAdministrationService
{
    public function __construct(
        private readonly JobAdministrationRepository $jobAdministrationRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function list(JobSearchCriteria $criteria): JobOverviewData
    {
        $summary = $this->jobAdministrationRepository->summary();

        return new JobOverviewData(
            readyJobs: $summary['ready_jobs'],
            reservedJobs: $summary['reserved_jobs'],
            failedJobs: $summary['failed_jobs'],
            pendingBatches: $summary['pending_batches'],
            items: $this->jobAdministrationRepository->listFailed($criteria->queue, $criteria->limit),
        );
    }

    public function retry(string $jobId): JobRetryData
    {
        $job = $this->jobAdministrationRepository->retryFailedJob($jobId);

        if ($job === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $result = new JobRetryData(
            job: $job,
            retriedAt: CarbonImmutable::now(),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'admin.job_retried',
            objectType: 'failed_job',
            objectId: $jobId,
            after: $result->toArray(),
        ));

        return $result;
    }
}
