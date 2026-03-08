<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class UpdateIpAllowlistCommand
{
    /**
     * @param  list<array{cidr: string, label: string|null}>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}
}
