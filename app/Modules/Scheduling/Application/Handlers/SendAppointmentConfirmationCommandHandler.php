<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\SendAppointmentConfirmationCommand;
use App\Modules\Scheduling\Application\Data\AppointmentNotificationDispatchData;
use App\Modules\Scheduling\Application\Services\AppointmentNotificationService;

final class SendAppointmentConfirmationCommandHandler
{
    public function __construct(
        private readonly AppointmentNotificationService $appointmentNotificationService,
    ) {}

    public function handle(SendAppointmentConfirmationCommand $command): AppointmentNotificationDispatchData
    {
        return $this->appointmentNotificationService->sendConfirmation($command->appointmentId);
    }
}
