<?php

namespace App\Modules\AuditCompliance\Application\Queries;

final readonly class GetDataAccessRequestQuery
{
    public function __construct(
        public string $requestId,
    ) {}
}
