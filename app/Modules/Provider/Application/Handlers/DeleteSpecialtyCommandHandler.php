<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\DeleteSpecialtyCommand;
use App\Modules\Provider\Application\Data\SpecialtyData;
use App\Modules\Provider\Application\Services\SpecialtyCatalogService;

final class DeleteSpecialtyCommandHandler
{
    public function __construct(
        private readonly SpecialtyCatalogService $specialtyCatalogService,
    ) {}

    public function handle(DeleteSpecialtyCommand $command): SpecialtyData
    {
        return $this->specialtyCatalogService->delete($command->specialtyId);
    }
}
