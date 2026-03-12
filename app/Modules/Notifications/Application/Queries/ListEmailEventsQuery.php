<?php

namespace App\Modules\Notifications\Application\Queries;

use App\Modules\Notifications\Application\Data\EmailEventListCriteria;

final readonly class ListEmailEventsQuery
{
    public function __construct(public EmailEventListCriteria $criteria) {}
}
