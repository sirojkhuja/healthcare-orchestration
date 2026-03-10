<?php

namespace App\Modules\Treatment\Application\Handlers;

use App\Modules\Treatment\Application\Commands\RemoveTreatmentItemCommand;
use App\Modules\Treatment\Application\Data\TreatmentItemData;
use App\Modules\Treatment\Application\Services\TreatmentItemService;

final class RemoveTreatmentItemCommandHandler
{
    public function __construct(
        private readonly TreatmentItemService $treatmentItemService,
    ) {}

    public function handle(RemoveTreatmentItemCommand $command): TreatmentItemData
    {
        return $this->treatmentItemService->delete($command->planId, $command->itemId);
    }
}
