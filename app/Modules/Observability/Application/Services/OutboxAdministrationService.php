<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Observability\Application\Data\OutboxDrainData;
use App\Modules\Observability\Application\Data\OutboxSearchCriteria;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Data\OutboxMessage;
use App\Shared\Application\Services\OutboxRelay;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OutboxAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OutboxRepository $outboxRepository,
        private readonly OutboxRelay $outboxRelay,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function drain(int $limit): OutboxDrainData
    {
        $result = $this->outboxRelay->drain($limit);
        $data = new OutboxDrainData(
            limit: $limit,
            claimed: $result->claimed,
            delivered: $result->delivered,
            failed: $result->failed,
            performedAt: CarbonImmutable::now(),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'admin.outbox_drained',
            objectType: 'outbox',
            objectId: $this->tenantContext->requireTenantId(),
            after: $data->toArray(),
        ));

        return $data;
    }

    /**
     * @return list<OutboxMessage>
     */
    public function list(OutboxSearchCriteria $criteria): array
    {
        return $this->outboxRepository->listForAdmin(
            $this->tenantContext->requireTenantId(),
            $criteria->status,
            $criteria->topic,
            $criteria->eventType,
            $criteria->limit,
        );
    }

    public function retry(string $outboxId): OutboxMessage
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $message = $this->outboxRepository->findForAdmin($outboxId, $tenantId);

        if (! $message instanceof OutboxMessage) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        if ($message->status !== 'failed') {
            throw new ConflictHttpException('Only failed outbox items may be retried.');
        }

        $retried = $this->outboxRepository->retry($outboxId);

        if (! $retried instanceof OutboxMessage) {
            throw new \LogicException('Retried outbox item could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'admin.outbox_retried',
            objectType: 'outbox',
            objectId: $outboxId,
            before: $message->toArray(),
            after: $retried->toArray(),
        ));

        return $retried;
    }
}
