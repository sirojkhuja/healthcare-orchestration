<?php

namespace App\Modules\Scheduling\Domain\Appointments;

use DateTimeImmutable;

final readonly class AppointmentTransitionData
{
    public function __construct(
        public AppointmentStatus $fromStatus,
        public AppointmentStatus $toStatus,
        public DateTimeImmutable $occurredAt,
        public AppointmentActor $actor,
        public ?string $reason = null,
        public bool $adminOverride = false,
        public ?AppointmentStatus $restoredFromStatus = null,
        public ?string $replacementAppointmentId = null,
        public ?AppointmentSlot $replacementSlot = null,
    ) {}

    /**
     * @return array{
     *     from_status: string,
     *     to_status: string,
     *     occurred_at: string,
     *     actor: array{type: string, id: string|null, name: string|null},
     *     reason: string|null,
     *     admin_override: bool,
     *     restored_from_status: string|null,
     *     replacement_appointment_id: string|null,
     *     replacement_slot: array{start_at: string, end_at: string, timezone: string}|null
     * }
     */
    public function toArray(): array
    {
        return [
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'actor' => $this->actor->toArray(),
            'reason' => $this->reason,
            'admin_override' => $this->adminOverride,
            'restored_from_status' => $this->restoredFromStatus?->value,
            'replacement_appointment_id' => $this->replacementAppointmentId,
            'replacement_slot' => $this->replacementSlot?->toArray(),
        ];
    }
}
