<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class BulkUpdateUsersCommand
{
    /**
     * @param  list<string>  $userIds
     */
    public function __construct(
        public string $action,
        public array $userIds,
    ) {}
}
