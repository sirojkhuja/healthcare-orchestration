<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\PaymentGatewayRegistry;
use App\Modules\Billing\Application\Contracts\PaymentReconciliationRunRepository;
use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentReconciliationResultData;
use App\Modules\Billing\Application\Data\PaymentReconciliationRunData;
use App\Modules\Billing\Domain\Payments\PaymentActor;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Str;

final class PaymentReconciliationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentGatewayRegistry $paymentGatewayRegistry,
        private readonly PaymentSnapshotSynchronizationService $paymentSnapshotSynchronizationService,
        private readonly PaymentReconciliationRunRepository $paymentReconciliationRunRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  list<string>  $paymentIds
     */
    public function reconcile(string $providerKey, array $paymentIds = [], int $limit = 50): PaymentReconciliationRunData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $gateway = $this->paymentGatewayRegistry->resolve($providerKey);
        $runId = (string) Str::uuid();
        $payments = $this->paymentRepository->listForReconciliation(
            tenantId: $tenantId,
            providerKey: $providerKey,
            statuses: ['initiated', 'pending', 'captured'],
            limit: $limit,
            paymentIds: $paymentIds,
        );
        $results = [];
        $changedCount = 0;
        $actor = new PaymentActor(type: 'system', name: sprintf('payment-reconciliation/%s', $providerKey));

        foreach ($payments as $payment) {
            $updated = $this->reconcilePayment($payment, $gateway->supportsRefunds(), $runId, $providerKey, $actor);
            $changed = $this->paymentChanged($payment, $updated);

            if ($changed) {
                $changedCount++;
            }

            $results[] = new PaymentReconciliationResultData(
                paymentId: $updated->paymentId,
                statusBefore: $payment->status,
                statusAfter: $updated->status,
                changed: $changed,
                providerPaymentId: $updated->providerPaymentId,
                providerStatus: $updated->providerStatus,
                failureCode: $updated->failureCode,
                failureMessage: $updated->failureMessage,
                payment: $updated->toArray(),
            );
        }

        $run = $this->paymentReconciliationRunRepository->create($tenantId, [
            'provider_key' => $providerKey,
            'requested_payment_ids' => $paymentIds,
            'scanned_count' => count($payments),
            'changed_count' => $changedCount,
            'result_count' => count($results),
            'results' => array_map(
                static fn (PaymentReconciliationResultData $result): array => $result->toArray(),
                $results,
            ),
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payments.reconciled',
            objectType: 'payment_reconciliation',
            objectId: $run->runId,
            after: $run->toArray(),
            metadata: [
                'provider_key' => $providerKey,
                'payment_ids' => array_map(
                    static fn (PaymentData $payment): string => $payment->paymentId,
                    $payments,
                ),
            ],
            tenantId: $tenantId,
        ));

        return $run;
    }

    private function paymentChanged(PaymentData $before, PaymentData $after): bool
    {
        return $before->status !== $after->status
            || $before->providerPaymentId !== $after->providerPaymentId
            || $before->providerStatus !== $after->providerStatus
            || $before->failureCode !== $after->failureCode
            || $before->failureMessage !== $after->failureMessage
            || $before->cancelReason !== $after->cancelReason
            || $before->refundReason !== $after->refundReason;
    }

    private function reconcilePayment(
        PaymentData $payment,
        bool $supportsRefunds,
        string $operationId,
        string $providerKey,
        PaymentActor $actor,
    ): PaymentData {
        $gateway = $this->paymentGatewayRegistry->resolve($providerKey);
        $snapshot = $gateway->fetchPaymentStatus($payment);

        return $this->paymentSnapshotSynchronizationService->synchronize(
            payment: $payment,
            snapshot: $snapshot,
            supportsRefunds: $supportsRefunds,
            actor: $actor,
            occurredAt: $snapshot->occurredAt,
            metadata: [
                'source' => 'reconciliation',
                'operation_id' => $operationId,
                'provider_key' => $providerKey,
            ],
            tenantId: $payment->tenantId,
        );
    }
}
