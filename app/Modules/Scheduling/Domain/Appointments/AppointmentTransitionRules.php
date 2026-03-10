<?php

namespace App\Modules\Scheduling\Domain\Appointments;

use DateTimeImmutable;

final class AppointmentTransitionRules
{
    public static function assertCanSchedule(AppointmentStatus $status): void
    {
        if ($status !== AppointmentStatus::DRAFT) {
            self::reject('Only draft appointments can be scheduled.');
        }
    }

    public static function assertCanConfirm(AppointmentStatus $status, AppointmentSlot $slot, DateTimeImmutable $occurredAt): void
    {
        if ($status !== AppointmentStatus::SCHEDULED) {
            self::reject('Only scheduled appointments can be confirmed.');
        }

        if ($slot->hasStartedAt($occurredAt)) {
            self::reject('Appointments scheduled in the past cannot be confirmed.');
        }
    }

    public static function assertCanCheckIn(AppointmentStatus $status, bool $adminOverride): void
    {
        if ($status === AppointmentStatus::CONFIRMED) {
            return;
        }

        if ($status === AppointmentStatus::SCHEDULED && $adminOverride) {
            return;
        }

        self::reject('Appointments can only be checked in after confirmation unless an admin override is recorded.');
    }

    public static function assertCanStart(AppointmentStatus $status): void
    {
        if ($status !== AppointmentStatus::CHECKED_IN) {
            self::reject('Only checked-in appointments can be started.');
        }
    }

    public static function assertCanComplete(AppointmentStatus $status): void
    {
        if ($status !== AppointmentStatus::IN_PROGRESS) {
            self::reject('Only in-progress appointments can be completed.');
        }
    }

    public static function assertCanCancel(AppointmentStatus $status, string $reason): void
    {
        self::assertReason($reason, 'Canceled appointments require a reason.');

        if (! in_array($status, [AppointmentStatus::SCHEDULED, AppointmentStatus::CONFIRMED], true)) {
            self::reject('Only scheduled or confirmed appointments can be canceled.');
        }
    }

    public static function assertCanMarkNoShow(
        AppointmentStatus $status,
        AppointmentSlot $slot,
        DateTimeImmutable $occurredAt,
        string $reason,
    ): void {
        self::assertReason($reason, 'No-show appointments require a reason.');

        if (! in_array($status, [AppointmentStatus::SCHEDULED, AppointmentStatus::CONFIRMED], true)) {
            self::reject('Only scheduled or confirmed appointments can be marked as no-show.');
        }

        if (! $slot->hasStartedAt($occurredAt)) {
            self::reject('Appointments can only become no-show after the scheduled start time.');
        }
    }

    public static function assertCanReschedule(
        AppointmentStatus $status,
        string $reason,
        AppointmentSlot $replacementSlot,
    ): void {
        self::assertReason($reason, 'Rescheduled appointments require a reason.');

        if (! in_array($status, [AppointmentStatus::SCHEDULED, AppointmentStatus::CONFIRMED], true)) {
            self::reject('Only scheduled or confirmed appointments can be rescheduled.');
        }
    }

    public static function assertCanRestore(
        AppointmentStatus $status,
        AppointmentSlot $slot,
        DateTimeImmutable $occurredAt,
    ): void {
        if (! $status->isRecoverableTerminal()) {
            self::reject('Only canceled, no-show, or rescheduled appointments can be restored.');
        }

        if ($slot->hasEndedAt($occurredAt)) {
            self::reject('Appointments cannot be restored after the original slot has fully elapsed.');
        }
    }

    private static function assertReason(string $reason, string $message): void
    {
        if (trim($reason) === '') {
            self::reject($message);
        }
    }

    private static function reject(string $message): never
    {
        throw new InvalidAppointmentTransition($message);
    }
}
