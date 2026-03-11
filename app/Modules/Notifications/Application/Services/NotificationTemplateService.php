<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Notifications\Application\Contracts\NotificationTemplateRepository;
use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Data\NotificationTemplateDetailsData;
use App\Modules\Notifications\Application\Data\NotificationTemplateListCriteria;
use App\Shared\Application\Contracts\TenantContext;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class NotificationTemplateService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationTemplateRepository $notificationTemplateRepository,
        private readonly NotificationTemplateAttributeNormalizer $notificationTemplateAttributeNormalizer,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): NotificationTemplateData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->notificationTemplateAttributeNormalizer->normalizeCreate($attributes);
        $this->assertUniqueCode($tenantId, $normalized['code']);
        $template = $this->notificationTemplateRepository->create($tenantId, $normalized);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notification_templates.created',
            objectType: 'notification_template',
            objectId: $template->templateId,
            after: $template->toArray(),
        ));

        return $template;
    }

    public function delete(string $templateId): NotificationTemplateData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $template = $this->templateOrFail($tenantId, $templateId);

        if (! $this->notificationTemplateRepository->delete($tenantId, $templateId)) {
            throw new LogicException('Notification template deletion did not remove the stored record.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notification_templates.deleted',
            objectType: 'notification_template',
            objectId: $template->templateId,
            before: $template->toArray(),
        ));

        return $template;
    }

    /**
     * @return list<NotificationTemplateData>
     */
    public function list(NotificationTemplateListCriteria $criteria): array
    {
        return $this->notificationTemplateRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    public function show(string $templateId): NotificationTemplateDetailsData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $template = $this->templateOrFail($tenantId, $templateId);

        return new NotificationTemplateDetailsData(
            template: $template,
            versions: $this->notificationTemplateRepository->listVersions($tenantId, $templateId),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $templateId, array $attributes): NotificationTemplateData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $template = $this->templateOrFail($tenantId, $templateId);
        $updates = $this->notificationTemplateAttributeNormalizer->normalizePatch($template, $attributes);

        if ($updates === []) {
            return $template;
        }

        /** @var mixed $candidateCode */
        $candidateCode = $updates['code'] ?? $template->code;
        $code = is_string($candidateCode) ? $candidateCode : $template->code;
        $this->assertUniqueCode($tenantId, $code, $templateId);
        $updated = $this->notificationTemplateRepository->update($tenantId, $templateId, $updates);

        if (! $updated instanceof NotificationTemplateData) {
            throw new LogicException('Updated notification template could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'notification_templates.updated',
            objectType: 'notification_template',
            objectId: $updated->templateId,
            before: $template->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function assertUniqueCode(string $tenantId, string $code, ?string $ignoreTemplateId = null): void
    {
        if ($this->notificationTemplateRepository->codeExists($tenantId, $code, $ignoreTemplateId)) {
            throw new UnprocessableEntityHttpException('The code field must be unique in the current tenant.');
        }
    }

    private function templateOrFail(string $tenantId, string $templateId): NotificationTemplateData
    {
        $template = $this->notificationTemplateRepository->findInTenant($tenantId, $templateId);

        if (! $template instanceof NotificationTemplateData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $template;
    }
}
