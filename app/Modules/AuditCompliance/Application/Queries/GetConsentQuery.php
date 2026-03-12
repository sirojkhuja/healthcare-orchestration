<?php

namespace App\Modules\AuditCompliance\Application\Queries;

final readonly class GetConsentQuery
{
    public function __construct(
        public string $consentId,
    ) {}
}
