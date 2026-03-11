<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\CreateClaimCommand;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Services\ClaimAdministrationService;

final readonly class CreateClaimCommandHandler
{
    public function __construct(
        private ClaimAdministrationService $service,
    ) {}

    public function handle(CreateClaimCommand $command): ClaimData
    {
        return $this->service->create($command->attributes);
    }
}
