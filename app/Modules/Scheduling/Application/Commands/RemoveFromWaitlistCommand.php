<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class RemoveFromWaitlistCommand
{
    public function __construct(
        public string $entryId,
    ) {}
}
