<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class CancelRecurrenceCommand
{
    public function __construct(
        public string $recurrenceId,
        public string $reason,
    ) {}
}
