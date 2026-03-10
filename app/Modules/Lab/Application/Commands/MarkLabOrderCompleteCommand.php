<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class MarkLabOrderCompleteCommand
{
    public function __construct(
        public string $orderId,
    ) {}
}
