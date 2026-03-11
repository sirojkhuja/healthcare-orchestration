<?php

namespace App\Modules\Notifications\Application\Queries;

use App\Modules\Notifications\Application\Data\NotificationTemplateListCriteria;

final readonly class ListTemplatesQuery
{
    public function __construct(
        public NotificationTemplateListCriteria $criteria,
    ) {}
}
