<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\CreatePayerCommand;
use App\Modules\Insurance\Application\Data\PayerData;
use App\Modules\Insurance\Application\Services\PayerCatalogService;

final readonly class CreatePayerCommandHandler
{
    public function __construct(
        private PayerCatalogService $service,
    ) {}

    public function handle(CreatePayerCommand $command): PayerData
    {
        return $this->service->create($command->attributes);
    }
}
