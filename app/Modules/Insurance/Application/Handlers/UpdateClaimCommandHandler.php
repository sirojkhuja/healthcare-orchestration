<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\UpdateClaimCommand;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Services\ClaimAdministrationService;

final readonly class UpdateClaimCommandHandler
{
    public function __construct(
        private ClaimAdministrationService $service,
    ) {}

    public function handle(UpdateClaimCommand $command): ClaimData
    {
        return $this->service->update($command->claimId, $command->attributes);
    }
}
