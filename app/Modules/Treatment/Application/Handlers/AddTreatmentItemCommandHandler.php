<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\AddTreatmentItemCommand;
use App\Modules\Treatment\Application\Data\TreatmentItemData;
use App\Modules\Treatment\Application\Services\TreatmentItemService;

final class AddTreatmentItemCommandHandler
{
    public function __construct(
        private readonly TreatmentItemService $treatmentItemService,
    ) {}

    public function handle(AddTreatmentItemCommand $command): TreatmentItemData
    {
        return $this->treatmentItemService->create($command->planId, $command->attributes);
    }
}
