<?php

namespace App\Modules\Pharmacy\Domain\Prescriptions;

use DateTimeImmutable;

final readonly class PrescriptionTransitionData
{
    public function __construct(
        public PrescriptionStatus $fromStatus,
        public PrescriptionStatus $toStatus,
        public DateTimeImmutable $occurredAt,
        public PrescriptionActor $actor,
        public ?string $reason = null,
    ) {}

    /**
     * @return array{
     *     from_status: string,
     *     to_status: string,
     *     occurred_at: string,
     *     actor: array{type: string, id: string|null, name: string|null},
     *     reason: string|null
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
        ];
    }
}
