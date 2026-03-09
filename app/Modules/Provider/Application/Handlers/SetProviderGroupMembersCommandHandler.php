<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\SetProviderGroupMembersCommand;
use App\Modules\Provider\Application\Data\ProviderGroupData;
use App\Modules\Provider\Application\Services\ProviderGroupService;

final class SetProviderGroupMembersCommandHandler
{
    public function __construct(
        private readonly ProviderGroupService $providerGroupService,
    ) {}

    public function handle(SetProviderGroupMembersCommand $command): ProviderGroupData
    {
        return $this->providerGroupService->setMembers($command->groupId, $command->providerIds);
    }
}
