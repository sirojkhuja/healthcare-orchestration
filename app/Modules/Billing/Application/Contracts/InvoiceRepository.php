<?php

namespace App\Modules\Billing\Application\Contracts;

use App\Modules\Billing\Application\Data\InvoiceData;
use App\Modules\Billing\Application\Data\InvoiceItemData;
use App\Modules\Billing\Application\Data\InvoiceSearchCriteria;
use Carbon\CarbonImmutable;

interface InvoiceRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): InvoiceData;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(string $tenantId, string $invoiceId, array $attributes): InvoiceItemData;

    public function findInTenant(string $tenantId, string $invoiceId, bool $withDeleted = false): ?InvoiceData;

    public function findItem(string $tenantId, string $invoiceId, string $itemId): ?InvoiceItemData;

    /**
     * @return list<InvoiceItemData>
     */
    public function listItems(string $tenantId, string $invoiceId): array;

    /**
     * @return list<InvoiceData>
     */
    public function search(string $tenantId, InvoiceSearchCriteria $criteria): array;

    public function softDelete(string $tenantId, string $invoiceId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $invoiceId, array $updates): ?InvoiceData;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function updateItem(string $tenantId, string $invoiceId, string $itemId, array $updates): ?InvoiceItemData;

    public function deleteItem(string $tenantId, string $invoiceId, string $itemId): bool;

    public function refreshTotals(string $tenantId, string $invoiceId): ?InvoiceData;

    public function allocateInvoiceNumber(string $tenantId): string;
}
