<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\UpdateTreatmentItemCommand;
use App\Modules\Treatment\Application\Data\TreatmentItemData;
use App\Modules\Treatment\Application\Services\TreatmentItemService;

final class UpdateTreatmentItemCommandHandler
{
    public function __construct(
        private readonly TreatmentItemService $treatmentItemService,
    ) {}

    public function handle(UpdateTreatmentItemCommand $command): TreatmentItemData
    {
        return $this->treatmentItemService->update($command->planId, $command->itemId, $command->attributes);
    }
}
