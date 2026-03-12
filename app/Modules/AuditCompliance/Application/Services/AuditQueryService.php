<?php

namespace App\Modules\AuditCompliance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Data\AuditEventSearchCriteria;
use App\Modules\AuditCompliance\Application\Data\AuditExportData;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AuditQueryService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditEventRepository $auditEventRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly FileStorageManager $fileStorageManager,
    ) {}

    /**
     * @return list<AuditEventData>
     */
    public function list(AuditEventSearchCriteria $criteria): array
    {
        return $this->auditEventRepository->search($criteria, $this->tenantContext->requireTenantId());
    }

    public function show(string $eventId): AuditEventData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $event = $this->auditEventRepository->findById($eventId);

        if (! $event instanceof AuditEventData || $event->tenantId !== $tenantId) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $event;
    }

    /**
     * @return list<AuditEventData>
     */
    public function objectHistory(string $objectType, string $objectId, ?string $actionPrefix, int $limit): array
    {
        return $this->list(new AuditEventSearchCriteria(
            actionPrefix: $actionPrefix,
            objectType: mb_strtolower(trim($objectType)),
            objectId: $objectId,
            limit: $limit,
        ));
    }

    public function export(AuditEventSearchCriteria $criteria, string $format): AuditExportData
    {
        if ($format !== 'csv') {
            throw new UnprocessableEntityHttpException('Audit export currently supports only csv format.');
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $events = $this->list($criteria);
        $generatedAt = CarbonImmutable::now();
        $exportId = (string) Str::uuid();
        $fileName = sprintf('audit-events-%s.csv', $generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($events, $generatedAt),
            sprintf('tenants/%s/audit/exports/%s/%s', $tenantId, $generatedAt->format('Y/m/d'), $fileName),
        );
        $export = new AuditExportData(
            exportId: $exportId,
            format: $format,
            fileName: $fileName,
            rowCount: count($events),
            generatedAt: $generatedAt,
            filters: $criteria,
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'audit.exported',
            objectType: 'audit_export',
            objectId: $exportId,
            after: $export->toArray(),
            metadata: [
                'tenant_id' => $tenantId,
                'filters' => $criteria->toArray(),
            ],
        ));

        return $export;
    }

    /**
     * @param  list<AuditEventData>  $events
     */
    private function buildCsv(array $events, CarbonImmutable $generatedAt): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new UnprocessableEntityHttpException('Audit export could not allocate a CSV stream.');
        }

        fputcsv($stream, [
            'event_id',
            'tenant_id',
            'action',
            'object_type',
            'object_id',
            'actor_type',
            'actor_id',
            'actor_name',
            'request_id',
            'correlation_id',
            'occurred_at',
            'before',
            'after',
            'metadata',
            'exported_at',
        ]);

        foreach ($events as $event) {
            fputcsv($stream, [
                $event->eventId,
                $event->tenantId,
                $event->action,
                $event->objectType,
                $event->objectId,
                $event->actor->type,
                $event->actor->id,
                $event->actor->name,
                $event->requestId,
                $event->correlationId,
                $event->occurredAt->toIso8601String(),
                json_encode($event->before, JSON_THROW_ON_ERROR),
                json_encode($event->after, JSON_THROW_ON_ERROR),
                json_encode($event->metadata, JSON_THROW_ON_ERROR),
                $generatedAt->toIso8601String(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new UnprocessableEntityHttpException('Audit export could not be generated.');
        }

        return $contents;
    }
}
