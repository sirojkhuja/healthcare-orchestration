<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\VerifyMyIdCommand;
use App\Modules\Integrations\Application\Data\MyIdVerificationData;
use App\Modules\Integrations\Application\Services\MyIdVerificationService;

final class VerifyMyIdCommandHandler
{
    public function __construct(
        private readonly MyIdVerificationService $myIdVerificationService,
    ) {}

    public function handle(VerifyMyIdCommand $command): MyIdVerificationData
    {
        return $this->myIdVerificationService->create($command->attributes);
    }
}
