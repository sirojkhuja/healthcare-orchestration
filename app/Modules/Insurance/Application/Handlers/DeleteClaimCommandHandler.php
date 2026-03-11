<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\DeleteClaimCommand;
use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Application\Services\ClaimAdministrationService;

final readonly class DeleteClaimCommandHandler
{
    public function __construct(
        private ClaimAdministrationService $service,
    ) {}

    public function handle(DeleteClaimCommand $command): ClaimData
    {
        return $this->service->delete($command->claimId);
    }
}
