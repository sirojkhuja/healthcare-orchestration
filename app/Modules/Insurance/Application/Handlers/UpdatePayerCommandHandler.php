<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\UpdatePayerCommand;
use App\Modules\Insurance\Application\Data\PayerData;
use App\Modules\Insurance\Application\Services\PayerCatalogService;

final readonly class UpdatePayerCommandHandler
{
    public function __construct(
        private PayerCatalogService $service,
    ) {}

    public function handle(UpdatePayerCommand $command): PayerData
    {
        return $this->service->update($command->payerId, $command->attributes);
    }
}
