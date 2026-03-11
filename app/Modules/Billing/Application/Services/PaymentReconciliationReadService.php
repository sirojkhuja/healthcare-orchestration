<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentReconciliationRunRepository;
use App\Modules\Billing\Application\Data\PaymentReconciliationRunData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentReconciliationReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PaymentReconciliationRunRepository $paymentReconciliationRunRepository,
    ) {}

    public function get(string $runId): PaymentReconciliationRunData
    {
        $run = $this->paymentReconciliationRunRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $runId,
        );

        if (! $run instanceof PaymentReconciliationRunData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $run;
    }

    /**
     * @return list<PaymentReconciliationRunData>
     */
    public function list(?string $providerKey = null, int $limit = 25): array
    {
        return $this->paymentReconciliationRunRepository->listInTenant(
            $this->tenantContext->requireTenantId(),
            $providerKey,
            $limit,
        );
    }
}
