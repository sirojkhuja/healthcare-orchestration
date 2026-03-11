<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentRepository;
use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentListCriteria;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PaymentRepository $paymentRepository,
    ) {}

    public function get(string $paymentId): PaymentData
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

    /**
     * @return list<PaymentData>
     */
    public function list(PaymentListCriteria $criteria): array
    {
        return $this->paymentRepository->search(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }
}
