<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\NotificationData;

interface NotificationQueueGateway
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function queue(array $attributes): NotificationData;
}
