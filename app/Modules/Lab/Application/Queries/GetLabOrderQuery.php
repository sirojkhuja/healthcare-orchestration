<?php

namespace App\Modules\Lab\Application\Queries;

final readonly class GetLabOrderQuery
{
    public function __construct(
        public string $orderId,
    ) {}
}
