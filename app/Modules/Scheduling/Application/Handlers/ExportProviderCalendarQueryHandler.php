<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Data\ProviderCalendarExportData;
use App\Modules\Scheduling\Application\Queries\ExportProviderCalendarQuery;
use App\Modules\Scheduling\Application\Services\ProviderCalendarService;

final class ExportProviderCalendarQueryHandler
{
    public function __construct(
        private readonly ProviderCalendarService $providerCalendarService,
    ) {}

    public function handle(ExportProviderCalendarQuery $query): ProviderCalendarExportData
    {
        return $this->providerCalendarService->export(
            providerId: $query->providerId,
            dateFrom: $query->dateFrom,
            dateTo: $query->dateTo,
            limit: $query->limit,
            format: $query->format,
        );
    }
}
