<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class CancelLabOrderCommand
{
    public function __construct(
        public string $orderId,
        public string $reason,
    ) {}
}
