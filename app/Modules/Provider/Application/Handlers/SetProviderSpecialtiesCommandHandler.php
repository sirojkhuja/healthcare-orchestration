<?php

namespace App\Modules\Provider\Application\Handlers;

use App\Modules\Provider\Application\Commands\SetProviderSpecialtiesCommand;
use App\Modules\Provider\Application\Data\ProviderSpecialtyData;
use App\Modules\Provider\Application\Services\SpecialtyCatalogService;

final class SetProviderSpecialtiesCommandHandler
{
    public function __construct(
        private readonly SpecialtyCatalogService $specialtyCatalogService,
    ) {}

    /**
     * @return list<ProviderSpecialtyData>
     */
    public function handle(SetProviderSpecialtiesCommand $command): array
    {
        return $this->specialtyCatalogService->setProviderSpecialties($command->providerId, $command->attributes);
    }
}
