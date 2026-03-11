<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\SmsDeliveryAttemptData;
use App\Modules\Notifications\Application\Data\SmsDeliveryRequestData;

interface SmsProvider
{
    public function providerKey(): string;

    public function providerName(): string;

    public function send(SmsDeliveryRequestData $request): SmsDeliveryAttemptData;
}
