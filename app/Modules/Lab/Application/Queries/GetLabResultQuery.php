<?php

namespace App\Modules\Lab\Application\Queries;

final readonly class GetLabResultQuery
{
    public function __construct(
        public string $orderId,
        public string $resultId,
    ) {}
}
