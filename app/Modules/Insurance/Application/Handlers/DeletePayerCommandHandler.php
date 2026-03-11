<?php

namespace App\Modules\Insurance\Application\Handlers;

use App\Modules\Insurance\Application\Commands\DeletePayerCommand;
use App\Modules\Insurance\Application\Data\PayerData;
use App\Modules\Insurance\Application\Services\PayerCatalogService;

final readonly class DeletePayerCommandHandler
{
    public function __construct(
        private PayerCatalogService $service,
    ) {}

    public function handle(DeletePayerCommand $command): PayerData
    {
        return $this->service->delete($command->payerId);
    }
}
