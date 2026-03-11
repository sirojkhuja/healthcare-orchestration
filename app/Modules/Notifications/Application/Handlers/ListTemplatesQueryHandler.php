<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Queries\ListTemplatesQuery;
use App\Modules\Notifications\Application\Services\NotificationTemplateService;

final readonly class ListTemplatesQueryHandler
{
    public function __construct(
        private NotificationTemplateService $service,
    ) {}

    /**
     * @return list<NotificationTemplateData>
     */
    public function handle(ListTemplatesQuery $query): array
    {
        return $this->service->list($query->criteria);
    }
}
