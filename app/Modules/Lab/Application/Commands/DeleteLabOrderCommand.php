<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class DeleteLabOrderCommand
{
    public function __construct(
        public string $orderId,
    ) {}
}
