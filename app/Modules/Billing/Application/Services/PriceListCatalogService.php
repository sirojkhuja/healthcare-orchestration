<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Billing\Application\Contracts\BillableServiceRepository;
use App\Modules\Billing\Application\Contracts\PriceListRepository;
use App\Modules\Billing\Application\Data\PriceListData;
use App\Modules\Billing\Application\Data\PriceListListCriteria;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class PriceListCatalogService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PriceListRepository $priceListRepository,
        private readonly BillableServiceRepository $billableServiceRepository,
        private readonly PriceListAttributeNormalizer $priceListAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PriceListData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->priceListAttributeNormalizer->normalizeCreate($attributes);
        /** @var mixed $candidateCode */
        $candidateCode = $normalized['code'] ?? null;
        $code = is_string($candidateCode) ? $candidateCode : '';
        $this->assertUniqueCode($tenantId, $code);
        $displacedDefaults = $normalized['is_default'] === true
            ? $this->priceListRepository->listDefaultsForTenant($tenantId)
            : [];

        if ($normalized['is_default'] === true) {
            $this->priceListRepository->clearDefaultFlags($tenantId);
        }

        $priceList = $this->priceListRepository->create($tenantId, $normalized);
        $this->recordDisplacedDefaultAudits($tenantId, $displacedDefaults);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'price_lists.created',
            objectType: 'price_list',
            objectId: $priceList->priceListId,
            after: $priceList->toArray(),
        ));

        return $priceList;
    }

    public function delete(string $priceListId): PriceListData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $priceList = $this->priceListOrFail($priceListId);

        if (! $this->priceListRepository->delete($tenantId, $priceListId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'price_lists.deleted',
            objectType: 'price_list',
            objectId: $priceList->priceListId,
            before: $priceList->toArray(),
        ));

        return $priceList;
    }

    public function get(string $priceListId): PriceListData
    {
        return $this->priceListOrFail($priceListId);
    }

    /**
     * @return list<PriceListData>
     */
    public function list(PriceListListCriteria $criteria): array
    {
        return $this->priceListRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function replaceItems(string $priceListId, array $items): PriceListData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $priceList = $this->priceListOrFail($priceListId);
        $normalizedItems = $this->priceListAttributeNormalizer->normalizeItems($items);
        $serviceIds = array_values(array_unique(array_map(
            static fn (array $item): string => $item['service_id'],
            $normalizedItems,
        )));

        if ($serviceIds !== []) {
            $services = $this->billableServiceRepository->listByIds($tenantId, $serviceIds);

            if (count($services) !== count($serviceIds)) {
                throw new NotFoundHttpException('One or more billable services do not exist in the current tenant scope.');
            }
        }

        $before = $priceList->toArray();
        $this->priceListRepository->replaceItems($tenantId, $priceListId, $normalizedItems);
        $reloaded = $this->priceListRepository->findInTenant($tenantId, $priceListId);

        if (! $reloaded instanceof PriceListData) {
            throw new \LogicException('Price list items could not be reloaded after replacement.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'price_lists.items_replaced',
            objectType: 'price_list',
            objectId: $reloaded->priceListId,
            before: $before,
            after: $reloaded->toArray(),
        ));

        return $reloaded;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $priceListId, array $attributes): PriceListData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $priceList = $this->priceListOrFail($priceListId);
        $updates = $this->priceListAttributeNormalizer->normalizePatch($priceList, $attributes);

        if ($updates === []) {
            return $priceList;
        }

        /** @var mixed $candidateCode */
        $candidateCode = $updates['code'] ?? $priceList->code;
        $code = is_string($candidateCode) ? $candidateCode : $priceList->code;
        $this->assertUniqueCode($tenantId, $code, $priceListId);
        $displacedDefaults = ($updates['is_default'] ?? false) === true
            ? $this->priceListRepository->listDefaultsForTenant($tenantId, $priceListId)
            : [];

        if (($updates['is_default'] ?? false) === true) {
            $this->priceListRepository->clearDefaultFlags($tenantId, $priceListId);
        }

        $updated = $this->priceListRepository->update($tenantId, $priceListId, $updates);

        if (! $updated instanceof PriceListData) {
            throw new \LogicException('Updated price list could not be reloaded.');
        }

        $this->recordDisplacedDefaultAudits($tenantId, $displacedDefaults);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'price_lists.updated',
            objectType: 'price_list',
            objectId: $updated->priceListId,
            before: $priceList->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function assertUniqueCode(string $tenantId, string $code, ?string $ignorePriceListId = null): void
    {
        if ($this->priceListRepository->codeExists($tenantId, $code, $ignorePriceListId)) {
            throw new UnprocessableEntityHttpException('The code field must be unique in the current tenant.');
        }
    }

    /**
     * @param  list<PriceListData>  $displacedDefaults
     */
    private function recordDisplacedDefaultAudits(string $tenantId, array $displacedDefaults): void
    {
        foreach ($displacedDefaults as $displacedDefault) {
            $reloaded = $this->priceListRepository->findInTenant($tenantId, $displacedDefault->priceListId);

            if (! $reloaded instanceof PriceListData) {
                continue;
            }

            if ($reloaded->isDefault === $displacedDefault->isDefault) {
                continue;
            }

            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'price_lists.updated',
                objectType: 'price_list',
                objectId: $reloaded->priceListId,
                before: $displacedDefault->toArray(),
                after: $reloaded->toArray(),
            ));
        }
    }

    private function priceListOrFail(string $priceListId): PriceListData
    {
        $priceList = $this->priceListRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $priceListId,
        );

        if (! $priceList instanceof PriceListData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $priceList;
    }
}
