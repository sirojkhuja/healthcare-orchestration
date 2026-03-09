<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class SetProviderGroupMembersCommand
{
    /**
     * @param  list<string>  $providerIds
     */
    public function __construct(
        public string $groupId,
        public array $providerIds,
    ) {}
}
