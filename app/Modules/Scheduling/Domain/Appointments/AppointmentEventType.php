<?php

namespace App\Modules\Scheduling\Domain\Appointments;

enum AppointmentEventType: string
{
    case SCHEDULED = 'appointment.scheduled';
    case CONFIRMED = 'appointment.confirmed';
    case CHECKED_IN = 'appointment.checked_in';
    case STARTED = 'appointment.started';
    case COMPLETED = 'appointment.completed';
    case CANCELED = 'appointment.canceled';
    case NO_SHOW = 'appointment.no_show';
    case RESCHEDULED = 'appointment.rescheduled';
    case RESTORED = 'appointment.restored';
}
