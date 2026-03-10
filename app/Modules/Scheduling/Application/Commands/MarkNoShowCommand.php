<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class MarkNoShowCommand
{
    public function __construct(
        public string $appointmentId,
        public string $reason,
    ) {}
}
