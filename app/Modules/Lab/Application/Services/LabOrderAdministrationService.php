<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Lab\Application\Contracts\LabOrderRepository;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Domain\LabOrders\LabOrderStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LabOrderAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LabOrderRepository $labOrderRepository,
        private readonly LabOrderAttributeNormalizer $labOrderAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly LabOutboxPublisher $labOutboxPublisher,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LabOrderData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $order = $this->labOrderRepository->create(
            $tenantId,
            $this->labOrderAttributeNormalizer->normalizeCreate($attributes, $tenantId),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_orders.created',
            objectType: 'lab_order',
            objectId: $order->orderId,
            after: $order->toArray(),
        ));
        $this->labOutboxPublisher->publishOrderEvent('lab_order.created', $order);

        return $order;
    }

    public function delete(string $orderId): LabOrderData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $order = $this->orderOrFail($orderId);

        if (! in_array($order->status, [LabOrderStatus::DRAFT->value, LabOrderStatus::CANCELED->value], true)) {
            throw new ConflictHttpException('Only draft or canceled lab orders may be deleted through the CRUD endpoint.');
        }

        $deletedAt = CarbonImmutable::now();

        if (! $this->labOrderRepository->softDelete($tenantId, $orderId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->labOrderRepository->findInTenant($tenantId, $orderId, true);

        if (! $deleted instanceof LabOrderData) {
            throw new \LogicException('Deleted lab order could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_orders.deleted',
            objectType: 'lab_order',
            objectId: $deleted->orderId,
            before: $order->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    public function get(string $orderId): LabOrderData
    {
        return $this->orderOrFail($orderId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $orderId, array $attributes): LabOrderData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $order = $this->orderOrFail($orderId);

        if ($order->status !== LabOrderStatus::DRAFT->value) {
            throw new ConflictHttpException('Only draft lab orders may be updated through the CRUD endpoint.');
        }

        $updates = $this->labOrderAttributeNormalizer->normalizePatch($order, $attributes);

        if ($updates === []) {
            return $order;
        }

        $updated = $this->labOrderRepository->update($tenantId, $orderId, $updates);

        if (! $updated instanceof LabOrderData) {
            throw new \LogicException('Updated lab order could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_orders.updated',
            objectType: 'lab_order',
            objectId: $updated->orderId,
            before: $order->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function orderOrFail(string $orderId): LabOrderData
    {
        $order = $this->labOrderRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $orderId,
        );

        if (! $order instanceof LabOrderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $order;
    }
}
