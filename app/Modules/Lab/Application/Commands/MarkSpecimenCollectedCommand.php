<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class MarkSpecimenCollectedCommand
{
    public function __construct(
        public string $orderId,
    ) {}
}
