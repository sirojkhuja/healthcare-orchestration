<?php

namespace App\Modules\Insurance\Domain\Claims;

use DateTimeImmutable;

final readonly class ClaimTransitionData
{
    public function __construct(
        public ClaimStatus $fromStatus,
        public ClaimStatus $toStatus,
        public DateTimeImmutable $occurredAt,
        public ClaimActor $actor,
        public ?string $reason = null,
        public ?string $sourceEvidence = null,
        public ?string $amount = null,
    ) {}

    /**
     * @return array{
     *     from_status: string,
     *     to_status: string,
     *     occurred_at: string,
     *     actor: array{type: string, id: string|null, name: string|null},
     *     reason: string|null,
     *     source_evidence: string|null,
     *     amount: string|null
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
            'source_evidence' => $this->sourceEvidence,
            'amount' => $this->amount,
        ];
    }
}
