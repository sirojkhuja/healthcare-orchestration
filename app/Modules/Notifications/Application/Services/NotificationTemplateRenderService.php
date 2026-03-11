<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\NotificationTemplateRenderer;
use App\Modules\Notifications\Application\Contracts\NotificationTemplateRepository;
use App\Modules\Notifications\Application\Data\RenderedNotificationTemplateData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NotificationTemplateRenderService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationTemplateRepository $notificationTemplateRepository,
        private readonly NotificationTemplateRenderer $notificationTemplateRenderer,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $templateId, array $variables): RenderedNotificationTemplateData
    {
        $template = $this->notificationTemplateRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $templateId,
        );

        if ($template === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $this->notificationTemplateRenderer->render($template, $variables);
    }
}
