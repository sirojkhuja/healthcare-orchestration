<?php

namespace App\Modules\Insurance\Application\Services;

use App\Modules\Insurance\Application\Data\ClaimData;
use App\Modules\Insurance\Domain\Claims\Claim;
use App\Modules\Insurance\Domain\Claims\ClaimActor;
use App\Modules\Insurance\Domain\Claims\ClaimStatus;
use App\Modules\Insurance\Domain\Claims\ClaimTransitionData;
use Carbon\CarbonImmutable;

final class ClaimAggregateMapper
{
    public function fromData(ClaimData $claim): Claim
    {
        return Claim::reconstitute(
            claimId: $claim->claimId,
            tenantId: $claim->tenantId,
            billedAmount: $claim->billedAmount,
            status: ClaimStatus::from($claim->status),
            lastTransition: $this->lastTransition($claim->lastTransition),
            adjudicationHistory: $claim->adjudicationHistory,
            submittedAt: $claim->submittedAt?->toDateTimeImmutable(),
            reviewStartedAt: $claim->reviewStartedAt?->toDateTimeImmutable(),
            approvedAt: $claim->approvedAt?->toDateTimeImmutable(),
            deniedAt: $claim->deniedAt?->toDateTimeImmutable(),
            paidAt: $claim->paidAt?->toDateTimeImmutable(),
            approvedAmount: $claim->approvedAmount,
            paidAmount: $claim->paidAmount,
            denialReason: $claim->denialReason,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function lastTransition(?array $payload): ?ClaimTransitionData
    {
        if ($payload === null) {
            return null;
        }

        $actorPayload = $payload['actor'] ?? null;

        if (! is_array($actorPayload)) {
            return null;
        }

        return new ClaimTransitionData(
            fromStatus: ClaimStatus::from($this->stringOrDefault($payload['from_status'] ?? null, ClaimStatus::DRAFT->value)),
            toStatus: ClaimStatus::from($this->stringOrDefault($payload['to_status'] ?? null, ClaimStatus::DRAFT->value)),
            occurredAt: CarbonImmutable::parse($this->stringOrDefault($payload['occurred_at'] ?? null, CarbonImmutable::now()->toIso8601String()))
                ->toDateTimeImmutable(),
            actor: new ClaimActor(
                type: $this->stringOrDefault($actorPayload['type'] ?? null, 'user'),
                id: $this->nullableString($actorPayload['id'] ?? null),
                name: $this->nullableString($actorPayload['name'] ?? null),
            ),
            reason: $this->nullableString($payload['reason'] ?? null),
            sourceEvidence: $this->nullableString($payload['source_evidence'] ?? null),
            amount: $this->nullableString($payload['amount'] ?? null),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }
}
