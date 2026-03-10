<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class SendLabOrderCommand
{
    public function __construct(
        public string $orderId,
    ) {}
}
