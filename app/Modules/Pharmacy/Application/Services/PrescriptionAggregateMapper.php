<?php

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\Pharmacy\Application\Data\PrescriptionData;
use App\Modules\Pharmacy\Domain\Prescriptions\Prescription;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionActor;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionStatus;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionTransitionData;
use Carbon\CarbonImmutable;

final class PrescriptionAggregateMapper
{
    public function fromData(PrescriptionData $prescription): Prescription
    {
        return Prescription::reconstitute(
            prescriptionId: $prescription->prescriptionId,
            tenantId: $prescription->tenantId,
            status: PrescriptionStatus::from($prescription->status),
            lastTransition: $this->transitionData($prescription->lastTransition),
            issuedAt: $prescription->issuedAt?->toDateTimeImmutable(),
            dispensedAt: $prescription->dispensedAt?->toDateTimeImmutable(),
            canceledAt: $prescription->canceledAt?->toDateTimeImmutable(),
            cancelReason: $prescription->cancelReason,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function transitionData(?array $payload): ?PrescriptionTransitionData
    {
        if ($payload === null) {
            return null;
        }

        $actor = $this->normalizeAssocArray($payload['actor'] ?? null);

        return new PrescriptionTransitionData(
            fromStatus: PrescriptionStatus::from($this->stringValue($payload, 'from_status', PrescriptionStatus::DRAFT->value)),
            toStatus: PrescriptionStatus::from($this->stringValue($payload, 'to_status', PrescriptionStatus::DRAFT->value)),
            occurredAt: CarbonImmutable::parse($this->stringValue($payload, 'occurred_at', CarbonImmutable::now()->toIso8601String()))
                ->toDateTimeImmutable(),
            actor: new PrescriptionActor(
                type: $this->stringValue($actor, 'type', 'user'),
                id: $this->nullableString($actor, 'id'),
                name: $this->nullableString($actor, 'name'),
            ),
            reason: $this->nullableString($payload, 'reason'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        /** @psalm-suppress MixedAssignment */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        /** @psalm-suppress MixedAssignment */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
