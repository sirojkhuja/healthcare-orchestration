<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\EmailEventData;
use App\Modules\Notifications\Application\Data\EmailEventListCriteria;

interface EmailEventRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(string $tenantId, array $attributes): EmailEventData;

    /**
     * @return list<EmailEventData>
     */
    public function search(string $tenantId, EmailEventListCriteria $criteria): array;
}
