<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\InvoiceRepository;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Domain\Invoices\InvoiceStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class InvoiceAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceAttributeNormalizer $invoiceAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly InvoiceOutboxPublisher $invoiceOutboxPublisher,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): InvoiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $invoiceNumber = $this->invoiceRepository->allocateInvoiceNumber($tenantId);
        $invoice = $this->invoiceRepository->create(
            $tenantId,
            $this->invoiceAttributeNormalizer->normalizeCreate($attributes, $tenantId, $invoiceNumber),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'invoices.created',
            objectType: 'invoice',
            objectId: $invoice->invoiceId,
            after: $invoice->toArray(),
        ));
        $this->invoiceOutboxPublisher->publishInvoiceEvent('invoice.created', $invoice);

        return $invoice;
    }

    public function delete(string $invoiceId): InvoiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $invoice = $this->invoiceOrFail($invoiceId);

        if (! in_array($invoice->status, [InvoiceStatus::DRAFT->value, InvoiceStatus::VOID->value], true)) {
            throw new ConflictHttpException('Only draft or void invoices may be deleted through the CRUD endpoint.');
        }

        $deletedAt = CarbonImmutable::now();

        if (! $this->invoiceRepository->softDelete($tenantId, $invoiceId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->invoiceRepository->findInTenant($tenantId, $invoiceId, true);

        if (! $deleted instanceof InvoiceData) {
            throw new LogicException('Deleted invoice could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'invoices.deleted',
            objectType: 'invoice',
            objectId: $deleted->invoiceId,
            before: $invoice->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    public function get(string $invoiceId): InvoiceData
    {
        return $this->invoiceOrFail($invoiceId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $invoiceId, array $attributes): InvoiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $invoice = $this->invoiceOrFail($invoiceId);

        if ($invoice->status !== InvoiceStatus::DRAFT->value) {
            throw new ConflictHttpException('Only draft invoices may be updated through the CRUD endpoint.');
        }

        $updates = $this->invoiceAttributeNormalizer->normalizePatch($invoice, $attributes);

        if ($updates === []) {
            return $invoice;
        }

        $updated = $this->invoiceRepository->update($tenantId, $invoiceId, $updates);

        if (! $updated instanceof InvoiceData) {
            throw new LogicException('Updated invoice could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'invoices.updated',
            objectType: 'invoice',
            objectId: $updated->invoiceId,
            before: $invoice->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function invoiceOrFail(string $invoiceId): InvoiceData
    {
        $invoice = $this->invoiceRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $invoiceId,
        );

        if (! $invoice instanceof InvoiceData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $invoice;
    }
}
