<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Data\NotificationTemplateDetailsData;
use App\Modules\Notifications\Application\Queries\GetTemplateQuery;
use App\Modules\Notifications\Application\Services\NotificationTemplateService;

final readonly class GetTemplateQueryHandler
{
    public function __construct(
        private NotificationTemplateService $service,
    ) {}

    public function handle(GetTemplateQuery $query): NotificationTemplateDetailsData
    {
        return $this->service->show($query->templateId);
    }
}
