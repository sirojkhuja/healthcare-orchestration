<?php

namespace App\Modules\Pharmacy\Application\Handlers;

use App\Modules\Pharmacy\Application\Commands\IssuePrescriptionCommand;
use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Application\Services\PrescriptionWorkflowService;

final class IssuePrescriptionCommandHandler
{
    public function __construct(
        private readonly PrescriptionWorkflowService $prescriptionWorkflowService,
    ) {}

    public function handle(IssuePrescriptionCommand $command): PrescriptionData
    {
        return $this->prescriptionWorkflowService->issue($command->prescriptionId);
    }
}
