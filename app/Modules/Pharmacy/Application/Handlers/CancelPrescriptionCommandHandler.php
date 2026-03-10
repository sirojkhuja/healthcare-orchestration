<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\CancelPrescriptionCommand;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Services\PrescriptionWorkflowService;

final class CancelPrescriptionCommandHandler
{
    public function __construct(
        private readonly PrescriptionWorkflowService $prescriptionWorkflowService,
    ) {}

    public function handle(CancelPrescriptionCommand $command): PrescriptionData
    {
        return $this->prescriptionWorkflowService->cancel($command->prescriptionId, $command->reason);
    }
}
