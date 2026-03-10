<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class MarkSpecimenReceivedCommand
{
    public function __construct(
        public string $orderId,
    ) {}
}
