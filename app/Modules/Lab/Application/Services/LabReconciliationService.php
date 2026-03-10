<?php

namespace App\Modules\Lab\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Lab\Application\Contracts\LabOrderRepository;
use App\Modules\Lab\Application\Contracts\LabProviderGatewayRegistry;
use App\Modules\Lab\Application\Data\LabOrderData;
use App\Modules\Lab\Application\Data\ReconcileLabOrdersData;
use App\Modules\Lab\Domain\LabOrders\LabOrderActor;
use App\Modules\Lab\Domain\LabOrders\LabOrderStatus;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Str;

final class LabReconciliationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly LabOrderRepository $labOrderRepository,
        private readonly LabProviderGatewayRegistry $labProviderGatewayRegistry,
        private readonly LabOrderWorkflowService $labOrderWorkflowService,
        private readonly LabOutboxPublisher $labOutboxPublisher,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  list<string>  $orderIds
     */
    public function reconcile(string $labProviderKey, array $orderIds = [], int $limit = 50): ReconcileLabOrdersData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $gateway = $this->labProviderGatewayRegistry->resolve($labProviderKey);
        $orders = $this->labOrderRepository->listForReconciliation(
            tenantId: $tenantId,
            labProviderKey: $labProviderKey,
            statuses: LabOrderStatus::reconcilable(),
            limit: $limit,
            orderIds: $orderIds,
        );
        $operationId = (string) Str::uuid();
        $resultCount = 0;
        $syncedOrders = [];
        $actor = new LabOrderActor(type: 'system', name: sprintf('lab-reconciliation/%s', $labProviderKey));

        foreach ($orders as $order) {
            $snapshot = $gateway->reconcileOrder($order);
            $sync = $this->labOrderWorkflowService->synchronizeRemoteSnapshot(
                order: $order,
                snapshot: $snapshot,
                actor: $actor,
                metadata: [
                    'source' => 'reconciliation',
                    'operation_id' => $operationId,
                    'provider_key' => $labProviderKey,
                ],
            );
            $syncedOrders[] = $sync->order;
            $resultCount += $sync->resultCount;
            $this->labOutboxPublisher->publishOrderEvent('lab_order.reconciled', $sync->order, [
                'operation_id' => $operationId,
                'provider_key' => $labProviderKey,
            ]);
        }

        $reconciliation = new ReconcileLabOrdersData(
            operationId: $operationId,
            labProviderKey: $labProviderKey,
            affectedCount: count($syncedOrders),
            resultCount: $resultCount,
            orders: $syncedOrders,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'lab_orders.reconciled',
            objectType: 'lab_reconciliation',
            objectId: $operationId,
            after: $reconciliation->toArray(),
            metadata: [
                'provider_key' => $labProviderKey,
                'order_ids' => array_map(
                    static fn (LabOrderData $order): string => $order->orderId,
                    $syncedOrders,
                ),
            ],
            tenantId: $tenantId,
        ));

        return $reconciliation;
    }
}
