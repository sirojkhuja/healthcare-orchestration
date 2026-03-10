<?php

namespace App\Modules\Lab\Domain\LabOrders;

use DateTimeImmutable;

final readonly class LabOrderTransitionData
{
    public function __construct(
        public LabOrderStatus $fromStatus,
        public LabOrderStatus $toStatus,
        public DateTimeImmutable $occurredAt,
        public LabOrderActor $actor,
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
