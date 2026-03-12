<?php

namespace App\Modules\AuditCompliance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\ConsentViewRepository;
use App\Modules\AuditCompliance\Application\Data\ConsentViewData;
use App\Modules\AuditCompliance\Application\Data\ConsentViewSearchCriteria;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ConsentViewService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ConsentViewRepository $consentViewRepository,
    ) {}

    public function get(string $consentId): ConsentViewData
    {
        $consent = $this->consentViewRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $consentId,
        );

        if (! $consent instanceof ConsentViewData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $consent;
    }

    /**
     * @return list<ConsentViewData>
     */
    public function list(ConsentViewSearchCriteria $criteria): array
    {
        return $this->consentViewRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }
}
