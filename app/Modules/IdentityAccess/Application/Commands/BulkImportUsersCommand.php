<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class BulkImportUsersCommand
{
    /**
     * @param  list<array{name: string, email: string, password?: string|null, status?: string|null}>  $users
     */
    public function __construct(
        public array $users,
    ) {}
}
