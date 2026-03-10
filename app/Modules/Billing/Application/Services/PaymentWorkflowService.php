<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Domain\Payments\InvalidPaymentTransition;
use App\Modules\Billing\Domain\Payments\Payment;
use App\Modules\Billing\Domain\Payments\PaymentActor;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentWorkflowService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PaymentRepository $paymentRepository,
        private readonly PaymentAggregateMapper $paymentAggregateMapper,
        private readonly PaymentActorContext $paymentActorContext,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly PaymentOutboxPublisher $paymentOutboxPublisher,
    ) {}

    public function cancel(
        string $paymentId,
        ?string $reason = null,
        ?string $providerStatus = null,
        ?PaymentActor $actor = null,
    ): PaymentData {
        return $this->transition(
            paymentId: $paymentId,
            auditAction: 'payments.canceled',
            eventType: 'payment.canceled',
            actor: $actor,
            additionalUpdates: $this->optionalUpdates([
                'provider_status' => $providerStatus,
            ]),
            mutator: static function (Payment $payment, CarbonImmutable $occurredAt, PaymentActor $resolvedActor) use ($reason): void {
                $payment->cancel($occurredAt->toDateTimeImmutable(), $resolvedActor, $reason);
            },
        );
    }

    public function capture(
        string $paymentId,
        ?string $providerStatus = null,
        ?PaymentActor $actor = null,
    ): PaymentData {
        return $this->transition(
            paymentId: $paymentId,
            auditAction: 'payments.captured',
            eventType: 'payment.captured',
            actor: $actor,
            additionalUpdates: $this->optionalUpdates([
                'provider_status' => $providerStatus,
            ]),
            mutator: static function (Payment $payment, CarbonImmutable $occurredAt, PaymentActor $resolvedActor): void {
                $payment->capture($occurredAt->toDateTimeImmutable(), $resolvedActor);
            },
        );
    }

    public function fail(
        string $paymentId,
        ?string $failureCode = null,
        ?string $failureMessage = null,
        ?string $providerStatus = null,
        ?PaymentActor $actor = null,
    ): PaymentData {
        return $this->transition(
            paymentId: $paymentId,
            auditAction: 'payments.failed',
            eventType: 'payment.failed',
            actor: $actor,
            additionalUpdates: $this->optionalUpdates([
                'provider_status' => $providerStatus,
            ]),
            mutator: static function (Payment $payment, CarbonImmutable $occurredAt, PaymentActor $resolvedActor) use ($failureCode, $failureMessage): void {
                $payment->fail($occurredAt->toDateTimeImmutable(), $resolvedActor, $failureCode, $failureMessage);
            },
        );
    }

    public function markPending(
        string $paymentId,
        ?string $providerPaymentId = null,
        ?string $providerStatus = null,
        ?string $checkoutUrl = null,
        ?PaymentActor $actor = null,
    ): PaymentData {
        return $this->transition(
            paymentId: $paymentId,
            auditAction: 'payments.pending',
            eventType: 'payment.pending',
            actor: $actor,
            additionalUpdates: $this->optionalUpdates([
                'provider_payment_id' => $providerPaymentId,
                'provider_status' => $providerStatus,
                'checkout_url' => $checkoutUrl,
            ]),
            mutator: static function (Payment $payment, CarbonImmutable $occurredAt, PaymentActor $resolvedActor): void {
                $payment->markPending($occurredAt->toDateTimeImmutable(), $resolvedActor);
            },
        );
    }

    public function refund(
        string $paymentId,
        bool $supportsRefunds,
        ?string $reason = null,
        ?string $providerStatus = null,
        ?PaymentActor $actor = null,
    ): PaymentData {
        return $this->transition(
            paymentId: $paymentId,
            auditAction: 'payments.refunded',
            eventType: 'payment.refunded',
            actor: $actor,
            additionalUpdates: $this->optionalUpdates([
                'provider_status' => $providerStatus,
            ]),
            mutator: static function (Payment $payment, CarbonImmutable $occurredAt, PaymentActor $resolvedActor) use ($supportsRefunds, $reason): void {
                $payment->refund($occurredAt->toDateTimeImmutable(), $resolvedActor, $supportsRefunds, $reason);
            },
        );
    }

    /**
     * @param  array<string, mixed>  $additionalUpdates
     * @param  callable(Payment, CarbonImmutable, PaymentActor): void  $mutator
     */
    private function transition(
        string $paymentId,
        string $auditAction,
        string $eventType,
        ?PaymentActor $actor,
        array $additionalUpdates,
        callable $mutator,
    ): PaymentData {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->paymentOrFail($tenantId, $paymentId);
        $resolvedActor = $actor ?? $this->paymentActorContext->current();
        $occurredAt = CarbonImmutable::now();

        /** @var PaymentData $updated */
        $updated = DB::transaction(function () use ($tenantId, $before, $resolvedActor, $occurredAt, $additionalUpdates, $mutator): PaymentData {
            $aggregate = $this->paymentAggregateMapper->fromData($before);

            try {
                $mutator($aggregate, $occurredAt, $resolvedActor);
            } catch (InvalidPaymentTransition $exception) {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

            $updated = $this->paymentRepository->update(
                $tenantId,
                $before->paymentId,
                [...$aggregate->snapshot(), ...$additionalUpdates],
            );

            if (! $updated instanceof PaymentData) {
                throw new LogicException('Updated payment could not be reloaded.');
            }

            return $updated;
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'payment',
            objectId: $updated->paymentId,
            before: $before->toArray(),
            after: $updated->toArray(),
        ));
        $this->paymentOutboxPublisher->publishPaymentEvent($eventType, $updated);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function optionalUpdates(array $updates): array
    {
        return array_filter(
            $updates,
            static fn (mixed $value): bool => $value !== null,
        );
    }

    private function paymentOrFail(string $tenantId, string $paymentId): PaymentData
    {
        $payment = $this->paymentRepository->findInTenant($tenantId, $paymentId);

        if (! $payment instanceof PaymentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $payment;
    }
}
