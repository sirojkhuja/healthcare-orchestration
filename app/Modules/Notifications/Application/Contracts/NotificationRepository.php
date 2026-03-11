<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\NotificationData;
use App\Modules\Notifications\Application\Data\NotificationListCriteria;

interface NotificationRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): NotificationData;

    public function findInTenant(string $tenantId, string $notificationId): ?NotificationData;

    /**
     * @return list<NotificationData>
     */
    public function search(string $tenantId, NotificationListCriteria $criteria): array;

    /**
     * @param  array<string, mixed>  $updates
     */
    public function update(string $tenantId, string $notificationId, array $updates): ?NotificationData;
}
