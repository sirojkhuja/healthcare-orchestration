<?php

namespace App\Modules\Integrations\Application\Handlers;

use App\Modules\Integrations\Application\Commands\CreateEImzoSignRequestCommand;
use App\Modules\Integrations\Application\Data\EImzoSignRequestData;
use App\Modules\Integrations\Application\Services\EImzoSigningService;

final class CreateEImzoSignRequestCommandHandler
{
    public function __construct(
        private readonly EImzoSigningService $eImzoSigningService,
    ) {}

    public function handle(CreateEImzoSignRequestCommand $command): EImzoSignRequestData
    {
        return $this->eImzoSigningService->create($command->attributes);
    }
}
