<?php

namespace App\Modules\Scheduling\Application\Handlers;

use App\Modules\Scheduling\Application\Commands\SendAppointmentReminderCommand;
use App\Modules\Scheduling\Application\Data\AppointmentNotificationDispatchData;
use App\Modules\Scheduling\Application\Services\AppointmentNotificationService;

final class SendAppointmentReminderCommandHandler
{
    public function __construct(
        private readonly AppointmentNotificationService $appointmentNotificationService,
    ) {}

    public function handle(SendAppointmentReminderCommand $command): AppointmentNotificationDispatchData
    {
        return $this->appointmentNotificationService->sendReminder($command->appointmentId);
    }
}
