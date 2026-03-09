<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\UpdateSpecialtyCommand;
use App\Modules\Provider\Application\Data\SpecialtyData;
use App\Modules\Provider\Application\Services\SpecialtyCatalogService;

final class UpdateSpecialtyCommandHandler
{
    public function __construct(
        private readonly SpecialtyCatalogService $specialtyCatalogService,
    ) {}

    public function handle(UpdateSpecialtyCommand $command): SpecialtyData
    {
        return $this->specialtyCatalogService->update($command->specialtyId, $command->attributes);
    }
}
