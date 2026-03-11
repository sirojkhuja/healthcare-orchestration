<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Data\ClaimExportData;
use App\Modules\Insurance\Application\Queries\ExportClaimsQuery;
use App\Modules\Insurance\Application\Services\ClaimReadService;

final readonly class ExportClaimsQueryHandler
{
    public function __construct(
        private ClaimReadService $service,
    ) {}

    public function handle(ExportClaimsQuery $query): ClaimExportData
    {
        return $this->service->export($query);
    }
}
