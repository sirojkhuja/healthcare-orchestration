<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\Data\EmailSendRequestData;
use App\Modules\Notifications\Application\Data\EmailSendResultData;

interface EmailGateway
{
    public function providerKey(): string;

    public function send(EmailSendRequestData $request): EmailSendResultData;
}
