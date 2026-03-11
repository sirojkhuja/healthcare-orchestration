<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentGatewayRegistry;
use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentGatewayInitiationRequestData;
use App\Modules\Billing\Application\Data\PaymentGatewaySnapshotData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentGatewayOperationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentGatewayRegistry $paymentGatewayRegistry,
        private readonly PaymentAdministrationService $paymentAdministrationService,
        private readonly PaymentWorkflowService $paymentWorkflowService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function initiate(array $attributes): PaymentData
    {
        $providerKey = array_key_exists('provider_key', $attributes) && is_string($attributes['provider_key'])
            ? strtolower(trim($attributes['provider_key']))
            : '';
        $gateway = $this->paymentGatewayRegistry->resolve($providerKey);
        $payment = $this->paymentAdministrationService->initiate($attributes);
        $snapshot = $gateway->initiatePayment(new PaymentGatewayInitiationRequestData(
            paymentId: $payment->paymentId,
            tenantId: $payment->tenantId,
            invoiceId: $payment->invoiceId,
            invoiceNumber: $payment->invoiceNumber,
            providerKey: $payment->providerKey,
            amount: $payment->amount,
            currency: $payment->currency,
            description: $payment->description,
        ));

        return $this->applySnapshot($payment, $snapshot, $gateway->supportsRefunds());
    }

    public function cancel(string $paymentId, ?string $reason = null): PaymentData
    {
        $payment = $this->paymentOrFail($paymentId);
        $gateway = $this->paymentGatewayRegistry->resolve($payment->providerKey);
        $snapshot = $gateway->cancelPayment($payment, $reason);

        return $this->applySnapshot($payment, $snapshot, $gateway->supportsRefunds(), $reason);
    }

    public function capture(string $paymentId): PaymentData
    {
        $payment = $this->paymentOrFail($paymentId);
        $gateway = $this->paymentGatewayRegistry->resolve($payment->providerKey);
        $snapshot = $gateway->capturePayment($payment);

        return $this->applySnapshot($payment, $snapshot, $gateway->supportsRefunds());
    }

    public function refund(string $paymentId, ?string $reason = null): PaymentData
    {
        $payment = $this->paymentOrFail($paymentId);
        $gateway = $this->paymentGatewayRegistry->resolve($payment->providerKey);
        $snapshot = $gateway->refundPayment($payment, $reason);

        return $this->applySnapshot($payment, $snapshot, $gateway->supportsRefunds(), $reason);
    }

    private function applySnapshot(
        PaymentData $payment,
        PaymentGatewaySnapshotData $snapshot,
        bool $supportsRefunds,
        ?string $reason = null,
    ): PaymentData {
        return match ($snapshot->status) {
            'initiated' => $payment,
            'pending' => $this->paymentWorkflowService->markPending(
                paymentId: $payment->paymentId,
                providerPaymentId: $snapshot->providerPaymentId,
                providerStatus: $snapshot->providerStatus,
                checkoutUrl: $snapshot->checkoutUrl,
            ),
            'captured' => $this->paymentWorkflowService->capture(
                paymentId: $payment->paymentId,
                providerStatus: $snapshot->providerStatus,
            ),
            'failed' => $this->paymentWorkflowService->fail(
                paymentId: $payment->paymentId,
                failureCode: $snapshot->failureCode,
                failureMessage: $snapshot->failureMessage,
                providerStatus: $snapshot->providerStatus,
            ),
            'canceled' => $this->paymentWorkflowService->cancel(
                paymentId: $payment->paymentId,
                reason: $snapshot->reason ?? $reason,
                providerStatus: $snapshot->providerStatus,
            ),
            'refunded' => $this->paymentWorkflowService->refund(
                paymentId: $payment->paymentId,
                supportsRefunds: $supportsRefunds,
                reason: $snapshot->reason ?? $reason,
                providerStatus: $snapshot->providerStatus,
            ),
            default => throw new ConflictHttpException('The payment gateway returned an unsupported payment status.'),
        };
    }

    private function paymentOrFail(string $paymentId): PaymentData
    {
        $payment = $this->paymentRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $paymentId,
        );

        if (! $payment instanceof PaymentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $payment;
    }
}
