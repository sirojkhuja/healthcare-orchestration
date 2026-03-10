<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Data\TreatmentItemData;
use App\Modules\Treatment\Application\Queries\ListTreatmentItemsQuery;
use App\Modules\Treatment\Application\Services\TreatmentItemService;

final class ListTreatmentItemsQueryHandler
{
    public function __construct(
        private readonly TreatmentItemService $treatmentItemService,
    ) {}

    /**
     * @return list<TreatmentItemData>
     */
    public function handle(ListTreatmentItemsQuery $query): array
    {
        return $this->treatmentItemService->list($query->planId);
    }
}
