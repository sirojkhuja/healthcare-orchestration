<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\BillableServiceRepository;
use App\Modules\Billing\Application\Contracts\InvoiceRepository;
use App\Modules\Billing\Application\Contracts\PriceListRepository;
use App\Modules\Billing\Application\Data\BillableServiceData;
use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Data\InvoiceItemData;
use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Domain\Invoices\InvoiceStatus;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InvoiceItemService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly BillableServiceRepository $billableServiceRepository,
        private readonly PriceListRepository $priceListRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function add(string $invoiceId, array $attributes): InvoiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $invoice = $this->draftInvoiceOrFail($invoiceId);
        $payload = $this->createPayload($tenantId, $invoice, $attributes);

        /** @var array{0: InvoiceItemData, 1: InvoiceData} $result */
        $result = DB::transaction(function () use ($tenantId, $invoiceId, $payload): array {
            $item = $this->invoiceRepository->createItem($tenantId, $invoiceId, $payload);
            $updated = $this->invoiceRepository->refreshTotals($tenantId, $invoiceId);

            if (! $updated instanceof InvoiceData) {
                throw new LogicException('Invoice totals could not be refreshed after adding an item.');
            }

            return [$item, $updated];
        });
        [$item, $updated] = $result;

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'invoice_items.created',
            objectType: 'invoice_item',
            objectId: $item->invoiceItemId,
            after: $item->toArray(),
            metadata: [
                'invoice_id' => $updated->invoiceId,
            ],
        ));

        return $updated;
    }

    /**
     * @return list<InvoiceItemData>
     */
    public function list(string $invoiceId): array
    {
        $invoice = $this->invoiceOrFail($invoiceId);

        return $this->invoiceRepository->listItems($invoice->tenantId, $invoice->invoiceId);
    }

    public function remove(string $invoiceId, string $itemId): InvoiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $invoice = $this->draftInvoiceOrFail($invoiceId);
        $item = $this->itemOrFail($invoice->invoiceId, $itemId);

        /** @var InvoiceData $updated */
        $updated = DB::transaction(function () use ($tenantId, $invoiceId, $itemId): InvoiceData {
            if (! $this->invoiceRepository->deleteItem($tenantId, $invoiceId, $itemId)) {
                throw new NotFoundHttpException('The requested invoice item does not exist in the current tenant scope.');
            }

            $updated = $this->invoiceRepository->refreshTotals($tenantId, $invoiceId);

            if (! $updated instanceof InvoiceData) {
                throw new LogicException('Invoice totals could not be refreshed after deleting an item.');
            }

            return $updated;
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'invoice_items.deleted',
            objectType: 'invoice_item',
            objectId: $item->invoiceItemId,
            before: $item->toArray(),
            metadata: [
                'invoice_id' => $updated->invoiceId,
            ],
        ));

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $invoiceId, string $itemId, array $attributes): InvoiceData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $invoice = $this->draftInvoiceOrFail($invoiceId);
        $item = $this->itemOrFail($invoiceId, $itemId);
        $updates = $this->updatePayload($tenantId, $invoice, $item, $attributes);

        if ($updates === []) {
            return $invoice;
        }

        /** @var array{0: InvoiceItemData, 1: InvoiceData} $result */
        $result = DB::transaction(function () use ($tenantId, $invoiceId, $itemId, $updates): array {
            $updatedItem = $this->invoiceRepository->updateItem($tenantId, $invoiceId, $itemId, $updates);

            if (! $updatedItem instanceof InvoiceItemData) {
                throw new NotFoundHttpException('The requested invoice item does not exist in the current tenant scope.');
            }

            $updatedInvoice = $this->invoiceRepository->refreshTotals($tenantId, $invoiceId);

            if (! $updatedInvoice instanceof InvoiceData) {
                throw new LogicException('Invoice totals could not be refreshed after updating an item.');
            }

            return [$updatedItem, $updatedInvoice];
        });
        [$updatedItem, $updatedInvoice] = $result;

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'invoice_items.updated',
            objectType: 'invoice_item',
            objectId: $updatedItem->invoiceItemId,
            before: $item->toArray(),
            after: $updatedItem->toArray(),
            metadata: [
                'invoice_id' => $updatedInvoice->invoiceId,
            ],
        ));

        return $updatedInvoice;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function createPayload(string $tenantId, InvoiceData $invoice, array $attributes): array
    {
        $service = $this->serviceOrFail(
            $tenantId,
            $this->requiredString($attributes['service_id'] ?? null, 'service_id'),
        );
        $quantity = $this->decimal($attributes['quantity'] ?? null, 'quantity');
        $unitPrice = $this->resolveUnitPrice(
            invoice: $invoice,
            serviceId: $service->serviceId,
            provided: $attributes['unit_price_amount'] ?? null,
        );

        return [
            'service_id' => $service->serviceId,
            'service_code' => $service->code,
            'service_name' => $service->name,
            'service_category' => $service->category,
            'service_unit' => $service->unit,
            'description' => $this->nullableString($attributes['description'] ?? null),
            'quantity' => $quantity,
            'unit_price_amount' => $unitPrice,
            'line_subtotal_amount' => $this->lineSubtotal($quantity, $unitPrice),
            'currency' => $invoice->currency,
        ];
    }

    private function decimal(mixed $value, string $field): string
    {
        $normalized = match (true) {
            is_int($value) => sprintf('%d.00', $value),
            is_float($value) => number_format($value, 2, '.', ''),
            is_string($value) => trim($value),
            default => '',
        };

        if ($normalized === '' || ! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $normalized)) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be a positive decimal with up to two fraction digits.', $field));
        }

        $parts = explode('.', $normalized, 2);
        $amount = $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');

        if ((float) $amount <= 0.0) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must be greater than zero.', $field));
        }

        return $amount;
    }

    private function draftInvoiceOrFail(string $invoiceId): InvoiceData
    {
        $invoice = $this->invoiceOrFail($invoiceId);

        if ($invoice->status !== InvoiceStatus::DRAFT->value) {
            throw new ConflictHttpException('Only draft invoices may mutate invoice items.');
        }

        return $invoice;
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

    private function itemOrFail(string $invoiceId, string $itemId): InvoiceItemData
    {
        $item = $this->invoiceRepository->findItem(
            $this->tenantContext->requireTenantId(),
            $invoiceId,
            $itemId,
        );

        if (! $item instanceof InvoiceItemData) {
            throw new NotFoundHttpException('The requested invoice item does not exist in the current tenant scope.');
        }

        return $item;
    }

    private function lineSubtotal(string $quantity, string $unitPrice): string
    {
        $quantityMinor = $this->minorUnits($quantity);
        $unitPriceMinor = $this->minorUnits($unitPrice);
        $subtotalMinor = intdiv($quantityMinor * $unitPriceMinor, 100);

        return number_format($subtotalMinor / 100, 2, '.', '');
    }

    private function minorUnits(string $amount): int
    {
        $parts = explode('.', $amount, 2);
        $whole = $parts[0];
        $decimal = $parts[1] ?? '';

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function priceListForPricing(InvoiceData $invoice): PriceListData
    {
        if ($invoice->priceListId === null) {
            throw new UnprocessableEntityHttpException('unit_price_amount is required when the invoice does not reference a price list.');
        }

        $priceList = $this->priceListRepository->findInTenant($invoice->tenantId, $invoice->priceListId);

        if (! $priceList instanceof PriceListData) {
            throw new UnprocessableEntityHttpException('The linked invoice price list no longer exists for price resolution.');
        }

        return $priceList;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $field));
        }

        return trim($value);
    }

    private function resolveUnitPrice(InvoiceData $invoice, string $serviceId, mixed $provided): string
    {
        if ($provided !== null && (! is_string($provided) || trim($provided) !== '')) {
            return $this->decimal($provided, 'unit_price_amount');
        }

        $priceList = $this->priceListForPricing($invoice);

        foreach ($priceList->items as $item) {
            if ($item->serviceId === $serviceId) {
                return $item->amount;
            }
        }

        throw new UnprocessableEntityHttpException('The selected service does not exist on the invoice price list.');
    }

    private function serviceOrFail(string $tenantId, string $serviceId): BillableServiceData
    {
        $service = $this->billableServiceRepository->findInTenant($tenantId, $serviceId);

        if (! $service instanceof BillableServiceData || ! $service->isActive) {
            throw new UnprocessableEntityHttpException('The service_id field must reference an active billable service in the current tenant.');
        }

        return $service;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function updatePayload(string $tenantId, InvoiceData $invoice, InvoiceItemData $item, array $attributes): array
    {
        $serviceId = array_key_exists('service_id', $attributes) ? $this->requiredString($attributes['service_id'], 'service_id') : $item->serviceId;
        $service = null;

        if ($serviceId !== $item->serviceId) {
            $service = $this->serviceOrFail($tenantId, $serviceId);
        }

        $quantity = array_key_exists('quantity', $attributes)
            ? $this->decimal($attributes['quantity'], 'quantity')
            : $item->quantity;
        /** @var mixed $providedPrice */
        $providedPrice = array_key_exists('unit_price_amount', $attributes)
            ? $attributes['unit_price_amount']
            : null;
        $unitPrice = $providedPrice !== null
            ? $this->decimal($providedPrice, 'unit_price_amount')
            : ($service !== null
                ? $this->resolveUnitPrice($invoice, $serviceId, null)
                : $item->unitPriceAmount);
        $description = array_key_exists('description', $attributes)
            ? $this->nullableString($attributes['description'])
            : $item->description;
        $candidate = [
            'service_id' => $serviceId,
            'service_code' => $service instanceof BillableServiceData ? $service->code : $item->serviceCode,
            'service_name' => $service instanceof BillableServiceData ? $service->name : $item->serviceName,
            'service_category' => $service instanceof BillableServiceData ? $service->category : $item->serviceCategory,
            'service_unit' => $service instanceof BillableServiceData ? $service->unit : $item->serviceUnit,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price_amount' => $unitPrice,
            'line_subtotal_amount' => $this->lineSubtotal($quantity, $unitPrice),
        ];
        $updates = [];
        $current = [
            'service_id' => $item->serviceId,
            'service_code' => $item->serviceCode,
            'service_name' => $item->serviceName,
            'service_category' => $item->serviceCategory,
            'service_unit' => $item->serviceUnit,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price_amount' => $item->unitPriceAmount,
            'line_subtotal_amount' => $item->lineSubtotalAmount,
        ];

        foreach ($candidate as $key => $value) {
            if ($value !== $current[$key]) {
                $updates[$key] = $value;
            }
        }

        return $updates;
    }
}
