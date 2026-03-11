<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentGatewayRegistry;
use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentGatewayInitiationRequestData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentGatewayOperationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentGatewayRegistry $paymentGatewayRegistry,
        private readonly PaymentAdministrationService $paymentAdministrationService,
        private readonly PaymentSnapshotSynchronizationService $paymentSnapshotSynchronizationService,
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

        return $this->paymentSnapshotSynchronizationService->synchronize(
            payment: $payment,
            snapshot: $snapshot,
            supportsRefunds: $gateway->supportsRefunds(),
        );
    }

    public function cancel(string $paymentId, ?string $reason = null): PaymentData
    {
        $payment = $this->paymentOrFail($paymentId);
        $gateway = $this->paymentGatewayRegistry->resolve($payment->providerKey);
        $snapshot = $gateway->cancelPayment($payment, $reason);

        return $this->paymentSnapshotSynchronizationService->synchronize(
            payment: $payment,
            snapshot: $snapshot,
            supportsRefunds: $gateway->supportsRefunds(),
            reason: $reason,
        );
    }

    public function capture(string $paymentId): PaymentData
    {
        $payment = $this->paymentOrFail($paymentId);
        $gateway = $this->paymentGatewayRegistry->resolve($payment->providerKey);
        $snapshot = $gateway->capturePayment($payment);

        return $this->paymentSnapshotSynchronizationService->synchronize(
            payment: $payment,
            snapshot: $snapshot,
            supportsRefunds: $gateway->supportsRefunds(),
        );
    }

    public function refund(string $paymentId, ?string $reason = null): PaymentData
    {
        $payment = $this->paymentOrFail($paymentId);
        $gateway = $this->paymentGatewayRegistry->resolve($payment->providerKey);
        $snapshot = $gateway->refundPayment($payment, $reason);

        return $this->paymentSnapshotSynchronizationService->synchronize(
            payment: $payment,
            snapshot: $snapshot,
            supportsRefunds: $gateway->supportsRefunds(),
            reason: $reason,
        );
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
