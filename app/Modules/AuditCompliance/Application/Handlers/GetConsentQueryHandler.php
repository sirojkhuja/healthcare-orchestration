<?php

namespace App\Modules\AuditCompliance\Application\Handlers;

use App\Modules\AuditCompliance\Application\Data\ConsentViewData;
use App\Modules\AuditCompliance\Application\Queries\GetConsentQuery;
use App\Modules\AuditCompliance\Application\Services\ConsentViewService;

final class GetConsentQueryHandler
{
    public function __construct(
        private readonly ConsentViewService $consentViewService,
    ) {}

    public function handle(GetConsentQuery $query): ConsentViewData
    {
        return $this->consentViewService->get($query->consentId);
    }
}
