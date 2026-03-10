<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\InvoiceRepository;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Domain\Invoices\InvalidInvoiceTransition;
use App\Modules\Billing\Domain\Invoices\Invoice;
use App\Modules\Billing\Domain\Invoices\InvoiceActor;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class InvoiceWorkflowService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceAggregateMapper $invoiceAggregateMapper,
        private readonly InvoiceActorContext $invoiceActorContext,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly InvoiceOutboxPublisher $invoiceOutboxPublisher,
    ) {}

    public function finalize(string $invoiceId): InvoiceData
    {
        return $this->transition(
            invoiceId: $invoiceId,
            auditAction: 'invoices.finalized',
            eventType: 'invoice.finalized',
            mutator: static function (Invoice $invoice, CarbonImmutable $occurredAt, InvoiceActor $actor): void {
                $invoice->finalize($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function issue(string $invoiceId): InvoiceData
    {
        return $this->transition(
            invoiceId: $invoiceId,
            auditAction: 'invoices.issued',
            eventType: 'invoice.issued',
            mutator: static function (Invoice $invoice, CarbonImmutable $occurredAt, InvoiceActor $actor): void {
                $invoice->issue($occurredAt->toDateTimeImmutable(), $actor);
            },
        );
    }

    public function void(string $invoiceId, string $reason): InvoiceData
    {
        return $this->transition(
            invoiceId: $invoiceId,
            auditAction: 'invoices.voided',
            eventType: 'invoice.voided',
            mutator: static function (Invoice $invoice, CarbonImmutable $occurredAt, InvoiceActor $actor) use ($reason): void {
                $invoice->void($occurredAt->toDateTimeImmutable(), $actor, $reason);
            },
        );
    }

    /**
     * @param  callable(Invoice, CarbonImmutable, InvoiceActor): void  $mutator
     */
    private function transition(string $invoiceId, string $auditAction, string $eventType, callable $mutator): InvoiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $before = $this->invoiceOrFail($tenantId, $invoiceId);
        $actor = $this->invoiceActorContext->current();
        $occurredAt = CarbonImmutable::now();

        /** @var InvoiceData $updated */
        $updated = DB::transaction(function () use ($tenantId, $before, $actor, $occurredAt, $mutator): InvoiceData {
            $aggregate = $this->invoiceAggregateMapper->fromData($before);

            try {
                $mutator($aggregate, $occurredAt, $actor);
            } catch (InvalidInvoiceTransition $exception) {
                throw new ConflictHttpException($exception->getMessage(), $exception);
            }

            $updated = $this->invoiceRepository->update($tenantId, $before->invoiceId, $aggregate->snapshot());

            if (! $updated instanceof InvoiceData) {
                throw new LogicException('Updated invoice could not be reloaded.');
            }

            return $updated;
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'invoice',
            objectId: $updated->invoiceId,
            before: $before->toArray(),
            after: $updated->toArray(),
        ));
        $this->invoiceOutboxPublisher->publishInvoiceEvent($eventType, $updated);

        return $updated;
    }

    private function invoiceOrFail(string $tenantId, string $invoiceId): InvoiceData
    {
        $invoice = $this->invoiceRepository->findInTenant($tenantId, $invoiceId);

        if (! $invoice instanceof InvoiceData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $invoice;
    }
}
