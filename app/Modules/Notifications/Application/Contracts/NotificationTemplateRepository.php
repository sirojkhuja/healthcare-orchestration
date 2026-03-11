<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Data\NotificationTemplateListCriteria;
use App\Modules\Notifications\Application\Data\NotificationTemplateVersionData;

interface NotificationTemplateRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $tenantId, array $attributes): NotificationTemplateData;

    public function delete(string $tenantId, string $templateId): bool;

    public function findInTenant(string $tenantId, string $templateId): ?NotificationTemplateData;

    public function findActiveByCode(string $tenantId, string $code): ?NotificationTemplateData;

    /**
     * @return list<NotificationTemplateData>
     */
    public function listForTenant(string $tenantId, NotificationTemplateListCriteria $criteria): array;

    /**
     * @return list<NotificationTemplateVersionData>
     */
    public function listVersions(string $tenantId, string $templateId): array;

    public function codeExists(string $tenantId, string $code, ?string $ignoreTemplateId = null): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $tenantId, string $templateId, array $attributes): ?NotificationTemplateData;
}
