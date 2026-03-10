<?php

use App\Modules\Scheduling\Domain\Appointments\Appointment;
use App\Modules\Scheduling\Domain\Appointments\AppointmentActor;
use App\Modules\Scheduling\Domain\Appointments\AppointmentEventType;
use App\Modules\Scheduling\Domain\Appointments\AppointmentSlot;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use App\Modules\Scheduling\Domain\Appointments\InvalidAppointmentTransition;
use Illuminate\Support\Str;

it('schedules a draft appointment and records a scheduled event', function (): void {
    $appointment = draftAppointment();

    $appointment->schedule(at('2026-03-10 09:00:00'), actor());

    expect($appointment->appointmentId())->not->toBe('');
    expect($appointment->status())->toBe(AppointmentStatus::SCHEDULED);
    expect($appointment->lastTransition()?->toArray())->toMatchArray([
        'from_status' => 'draft',
        'to_status' => 'scheduled',
        'admin_override' => false,
    ]);
    expect($appointment->snapshot()['status'])->toBe('scheduled');
    expect($appointment->snapshot()['scheduled_slot']['timezone'])->toBe('Asia/Tashkent');

    $events = $appointment->releaseRecordedEvents();

    expect($events)->toHaveCount(1);
    expect($events[0]->type)->toBe(AppointmentEventType::SCHEDULED);
    expect($events[0]->toArray())->toMatchArray([
        'event_type' => 'appointment.scheduled',
        'status' => 'scheduled',
    ]);
    expect($appointment->releaseRecordedEvents())->toBe([]);
});

it('exposes the documented appointment status catalog', function (): void {
    expect(AppointmentStatus::all())->toBe([
        'draft',
        'scheduled',
        'confirmed',
        'checked_in',
        'in_progress',
        'completed',
        'canceled',
        'no_show',
        'rescheduled',
    ]);
    expect(AppointmentStatus::COMPLETED->isTerminal())->toBeTrue();
    expect(AppointmentStatus::NO_SHOW->isRecoverableTerminal())->toBeTrue();
    expect(AppointmentStatus::SCHEDULED->isTerminal())->toBeFalse();
});

it('rejects invalid appointment actors and slot windows', function (): void {
    expect(fn (): AppointmentActor => new AppointmentActor(' ', null, null))
        ->toThrow(\InvalidArgumentException::class, 'Appointment actor type is required.');

    expect(fn (): AppointmentSlot => new AppointmentSlot(
        at('2026-03-10 10:00:00'),
        at('2026-03-10 10:00:00'),
        'Asia/Tashkent',
    ))->toThrow(\InvalidArgumentException::class, 'Appointment slot end time must be after the start time.');
});

it('confirms only scheduled appointments whose slot has not started', function (): void {
    $appointment = draftAppointment();
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());
    $appointment->confirm(at('2026-03-10 09:30:00'), actor());

    expect($appointment->status())->toBe(AppointmentStatus::CONFIRMED);
    expect($appointment->lastTransition()?->toArray())->toMatchArray([
        'from_status' => 'scheduled',
        'to_status' => 'confirmed',
    ]);

    $pastAppointment = draftAppointment(start: '2026-03-10 08:00:00', end: '2026-03-10 08:30:00');
    $pastAppointment->schedule(at('2026-03-10 07:00:00'), actor());

    expect(fn () => $pastAppointment->confirm(at('2026-03-10 08:00:00'), actor()))
        ->toThrow(InvalidAppointmentTransition::class, 'Appointments scheduled in the past cannot be confirmed.');
});

it('requires confirmation for check in unless an admin override is recorded', function (): void {
    $appointment = draftAppointment();
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());

    expect(fn () => $appointment->checkIn(at('2026-03-10 09:10:00'), actor()))
        ->toThrow(InvalidAppointmentTransition::class, 'Appointments can only be checked in after confirmation unless an admin override is recorded.');
});

it('allows admin override check in from the scheduled state', function (): void {
    $appointment = draftAppointment();
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());
    $appointment->checkIn(at('2026-03-10 09:10:00'), actor('user', 'admin-1', 'Admin User'), true);

    $event = $appointment->releaseRecordedEvents()[1];

    expect($appointment->status())->toBe(AppointmentStatus::CHECKED_IN);
    expect($appointment->lastTransition()?->toArray())->toMatchArray([
        'from_status' => 'scheduled',
        'to_status' => 'checked_in',
        'admin_override' => true,
    ]);
    expect($event->type)->toBe(AppointmentEventType::CHECKED_IN);
    expect($event->toArray()['transition']['admin_override'])->toBeTrue();
});

it('moves from checked in to completed only through in progress', function (): void {
    $appointment = draftAppointment();
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());
    $appointment->confirm(at('2026-03-10 09:10:00'), actor());
    $appointment->checkIn(at('2026-03-10 09:20:00'), actor());
    $appointment->start(at('2026-03-10 10:00:00'), actor());
    $appointment->complete(at('2026-03-10 10:20:00'), actor());

    $events = array_map(
        static fn ($event): string => $event->type->value,
        $appointment->releaseRecordedEvents(),
    );

    expect($appointment->status())->toBe(AppointmentStatus::COMPLETED);
    expect($events)->toBe([
        'appointment.scheduled',
        'appointment.confirmed',
        'appointment.checked_in',
        'appointment.started',
        'appointment.completed',
    ]);
});

it('rejects completion when the appointment has not started', function (): void {
    $appointment = draftAppointment();
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());
    $appointment->confirm(at('2026-03-10 09:05:00'), actor());
    $appointment->checkIn(at('2026-03-10 09:10:00'), actor());

    expect(fn () => $appointment->complete(at('2026-03-10 09:15:00'), actor()))
        ->toThrow(InvalidAppointmentTransition::class, 'Only in-progress appointments can be completed.');
});

it('requires a reason to cancel an appointment and only allows it from scheduled or confirmed', function (): void {
    $appointment = draftAppointment();
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());

    expect(fn () => $appointment->cancel(at('2026-03-10 09:05:00'), actor(), ' '))
        ->toThrow(InvalidAppointmentTransition::class, 'Canceled appointments require a reason.');

    $appointment->cancel(at('2026-03-10 09:06:00'), actor(), 'Patient requested another day');

    expect($appointment->status())->toBe(AppointmentStatus::CANCELED);
    expect($appointment->lastTransition()?->toArray())->toMatchArray([
        'to_status' => 'canceled',
        'reason' => 'Patient requested another day',
    ]);
});

it('marks appointments as no-show only after the scheduled start time and with a reason', function (): void {
    $appointment = draftAppointment();
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());

    expect(fn () => $appointment->markNoShow(at('2026-03-10 09:59:00'), actor(), 'Missed arrival'))
        ->toThrow(InvalidAppointmentTransition::class, 'Appointments can only become no-show after the scheduled start time.');

    $appointment->markNoShow(at('2026-03-10 10:00:00'), actor(), 'Missed arrival');

    expect($appointment->status())->toBe(AppointmentStatus::NO_SHOW);
    expect($appointment->lastTransition()?->toArray())->toMatchArray([
        'to_status' => 'no_show',
        'reason' => 'Missed arrival',
    ]);
});

it('records replacement slot metadata when an appointment is rescheduled', function (): void {
    $appointment = draftAppointment();
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());
    $appointment->confirm(at('2026-03-10 09:10:00'), actor());

    $replacementSlot = new AppointmentSlot(
        at('2026-03-11 11:00:00'),
        at('2026-03-11 11:30:00'),
        'Asia/Tashkent',
    );

    $appointment->reschedule(
        replacementSlot: $replacementSlot,
        occurredAt: at('2026-03-10 09:15:00'),
        actor: actor(),
        reason: 'Provider requested later slot',
        replacementAppointmentId: 'replacement-appointment',
    );

    expect($appointment->status())->toBe(AppointmentStatus::RESCHEDULED);
    expect($appointment->replacementAppointmentId())->toBe('replacement-appointment');
    expect($appointment->replacementSlot()?->toArray())->toBe($replacementSlot->toArray());
    expect($appointment->lastTransition()?->toArray())->toMatchArray([
        'to_status' => 'rescheduled',
        'reason' => 'Provider requested later slot',
        'replacement_appointment_id' => 'replacement-appointment',
    ]);
});

it('restores recoverable terminal appointments before the original slot ends', function (): void {
    $appointment = draftAppointment(end: '2026-03-10 10:45:00');
    $appointment->schedule(at('2026-03-10 09:00:00'), actor());
    $appointment->cancel(at('2026-03-10 09:10:00'), actor(), 'Entered by mistake');
    $appointment->restore(at('2026-03-10 10:15:00'), actor());

    expect($appointment->status())->toBe(AppointmentStatus::SCHEDULED);
    expect($appointment->replacementAppointmentId())->toBeNull();
    expect($appointment->replacementSlot())->toBeNull();
    expect($appointment->lastTransition()?->toArray())->toMatchArray([
        'from_status' => 'canceled',
        'to_status' => 'scheduled',
        'restored_from_status' => 'canceled',
    ]);
});

it('rejects restore from non recoverable states and after the original slot has elapsed', function (): void {
    $completedAppointment = draftAppointment();
    $completedAppointment->schedule(at('2026-03-10 09:00:00'), actor());
    $completedAppointment->confirm(at('2026-03-10 09:10:00'), actor());
    $completedAppointment->checkIn(at('2026-03-10 09:15:00'), actor());
    $completedAppointment->start(at('2026-03-10 10:00:00'), actor());
    $completedAppointment->complete(at('2026-03-10 10:20:00'), actor());

    expect(fn () => $completedAppointment->restore(at('2026-03-10 10:21:00'), actor()))
        ->toThrow(InvalidAppointmentTransition::class, 'Only canceled, no-show, or rescheduled appointments can be restored.');

    $elapsedAppointment = draftAppointment(start: '2026-03-10 08:00:00', end: '2026-03-10 08:30:00');
    $elapsedAppointment->schedule(at('2026-03-10 07:00:00'), actor());
    $elapsedAppointment->cancel(at('2026-03-10 07:15:00'), actor(), 'Duplicate entry');

    expect(fn () => $elapsedAppointment->restore(at('2026-03-10 09:00:00'), actor()))
        ->toThrow(InvalidAppointmentTransition::class, 'Appointments cannot be restored after the original slot has fully elapsed.');
});

function actor(string $type = 'user', ?string $id = 'user-1', ?string $name = 'Scheduler User'): AppointmentActor
{
    return new AppointmentActor($type, $id, $name);
}

function at(string $timestamp): \DateTimeImmutable
{
    return new \DateTimeImmutable($timestamp, new \DateTimeZone('Asia/Tashkent'));
}

function draftAppointment(
    string $start = '2026-03-10 10:00:00',
    string $end = '2026-03-10 10:30:00',
): Appointment {
    return Appointment::draft(
        appointmentId: (string) Str::uuid(),
        tenantId: (string) Str::uuid(),
        patientId: (string) Str::uuid(),
        providerId: (string) Str::uuid(),
        clinicId: (string) Str::uuid(),
        roomId: (string) Str::uuid(),
        scheduledSlot: new AppointmentSlot(
            at($start),
            at($end),
            'Asia/Tashkent',
        ),
    );
}
