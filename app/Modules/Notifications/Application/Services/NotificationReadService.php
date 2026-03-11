<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\NotificationRepository;
use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Data\NotificationListCriteria;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NotificationReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationRepository $notificationRepository,
    ) {}

    public function get(string $notificationId): NotificationData
    {
        $notification = $this->notificationRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $notificationId,
        );

        if (! $notification instanceof NotificationData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $notification;
    }

    /**
     * @return list<NotificationData>
     */
    public function list(NotificationListCriteria $criteria): array
    {
        return $this->notificationRepository->search(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }
}
