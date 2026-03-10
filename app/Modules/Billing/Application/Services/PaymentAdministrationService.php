<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;

final class PaymentAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentAttributeNormalizer $paymentAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly PaymentOutboxPublisher $paymentOutboxPublisher,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function initiate(array $attributes): PaymentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $initiatedAt = CarbonImmutable::now();
        $payment = $this->paymentRepository->create(
            $tenantId,
            $this->paymentAttributeNormalizer->normalizeInitiate($attributes, $tenantId, $initiatedAt),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'payments.initiated',
            objectType: 'payment',
            objectId: $payment->paymentId,
            after: $payment->toArray(),
        ));
        $this->paymentOutboxPublisher->publishPaymentEvent('payment.initiated', $payment);

        return $payment;
    }
}
