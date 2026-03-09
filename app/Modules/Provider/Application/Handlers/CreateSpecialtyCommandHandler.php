<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\CreateSpecialtyCommand;
use App\Modules\Provider\Application\Data\SpecialtyData;
use App\Modules\Provider\Application\Services\SpecialtyCatalogService;

final class CreateSpecialtyCommandHandler
{
    public function __construct(
        private readonly SpecialtyCatalogService $specialtyCatalogService,
    ) {}

    public function handle(CreateSpecialtyCommand $command): SpecialtyData
    {
        return $this->specialtyCatalogService->create($command->attributes);
    }
}
