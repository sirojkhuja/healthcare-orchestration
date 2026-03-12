<?php

namespace App\Modules\Notifications\Application\Handlers;

use App\Modules\Notifications\Application\Data\EmailEventData;
use App\Modules\Notifications\Application\Queries\ListEmailEventsQuery;
use App\Modules\Notifications\Application\Services\EmailEventReadService;

final class ListEmailEventsQueryHandler
{
    public function __construct(
        private readonly EmailEventReadService $emailEventReadService,
    ) {}

    /**
     * @return list<EmailEventData>
     */
    public function handle(ListEmailEventsQuery $query): array
    {
        return $this->emailEventReadService->list($query->criteria);
    }
}
