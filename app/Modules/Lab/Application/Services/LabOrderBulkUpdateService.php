<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Lab\Application\Contracts\LabOrderRepository;
use App\Modules\Lab\Application\Data\BulkLabOrderUpdateData;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Domain\LabOrders\LabOrderStatus;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class LabOrderBulkUpdateService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LabOrderRepository $labOrderRepository,
        private readonly LabOrderAttributeNormalizer $labOrderAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  list<string>  $orderIds
     * @param  array<string, mixed>  $changes
     */
    public function update(array $orderIds, array $changes): BulkLabOrderUpdateData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalizedIds = $this->normalizedIds($orderIds);
        $operationId = (string) Str::uuid();
        $mutations = [];

        /** @var list<LabOrderData> $orders */
        $orders = DB::transaction(function () use ($tenantId, $normalizedIds, $changes, &$mutations): array {
            $updated = [];

            foreach ($normalizedIds as $orderId) {
                $order = $this->orderOrFail($tenantId, $orderId);

                if ($order->status !== LabOrderStatus::DRAFT->value) {
                    throw new ConflictHttpException('Bulk lab order updates may target draft orders only.');
                }

                $updates = $this->labOrderAttributeNormalizer->normalizePatch($order, $changes);

                if ($updates === []) {
                    $updated[] = $order;

                    continue;
                }

                $result = $this->labOrderRepository->update($tenantId, $orderId, $updates);

                if (! $result instanceof LabOrderData) {
                    throw new \LogicException('Updated lab order could not be reloaded.');
                }

                $updated[] = $result;
                $mutations[] = [
                    'before' => $order,
                    'after' => $result,
                ];
            }

            return $updated;
        });

        if ($mutations !== []) {
            $updatedFields = array_keys($changes);
            $orderIdsForAudit = array_map(
                static fn (LabOrderData $order): string => $order->orderId,
                $orders,
            );
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'lab_orders.bulk_updated',
                objectType: 'lab_order_bulk_operation',
                objectId: $operationId,
                after: [
                    'operation_id' => $operationId,
                    'order_ids' => $orderIdsForAudit,
                    'updated_fields' => $updatedFields,
                    'affected_count' => count($orders),
                ],
            ));

            foreach ($mutations as $mutation) {
                $this->auditTrailWriter->record(new AuditRecordInput(
                    action: 'lab_orders.updated',
                    objectType: 'lab_order',
                    objectId: $mutation['after']->orderId,
                    before: $mutation['before']->toArray(),
                    after: $mutation['after']->toArray(),
                    metadata: [
                        'source' => 'bulk_update',
                        'bulk_operation_id' => $operationId,
                    ],
                ));
            }
        }

        return new BulkLabOrderUpdateData(
            operationId: $operationId,
            affectedCount: count($orders),
            updatedFields: array_keys($changes),
            orders: $orders,
        );
    }

    /**
     * @param  list<string>  $orderIds
     * @return list<string>
     */
    private function normalizedIds(array $orderIds): array
    {
        $normalized = array_values(array_filter(
            $orderIds,
            static fn (string $orderId): bool => $orderId !== '',
        ));

        if ($normalized === []) {
            throw new UnprocessableEntityHttpException('Bulk lab order updates require at least one order id.');
        }

        if (count($normalized) > 100) {
            throw new UnprocessableEntityHttpException('Bulk lab order updates may target at most 100 lab orders.');
        }

        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new UnprocessableEntityHttpException('Bulk lab order updates require distinct order ids.');
        }

        return $normalized;
    }

    private function orderOrFail(string $tenantId, string $orderId): LabOrderData
    {
        $order = $this->labOrderRepository->findInTenant($tenantId, $orderId);

        if (! $order instanceof LabOrderData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $order;
    }
}
