<?php

namespace App\Modules\Treatment\Domain\TreatmentPlans;

use DateTimeImmutable;

final readonly class TreatmentPlanTransitionData
{
    public function __construct(
        public TreatmentPlanStatus $fromStatus,
        public TreatmentPlanStatus $toStatus,
        public DateTimeImmutable $occurredAt,
        public TreatmentPlanActor $actor,
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

    /**
     * @param  array{
     *     from_status?: mixed,
     *     to_status?: mixed,
     *     occurred_at?: mixed,
     *     actor?: mixed,
     *     reason?: mixed
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            fromStatus: TreatmentPlanStatus::from(self::stringValue($payload, 'from_status', TreatmentPlanStatus::DRAFT->value)),
            toStatus: TreatmentPlanStatus::from(self::stringValue($payload, 'to_status', TreatmentPlanStatus::DRAFT->value)),
            occurredAt: new DateTimeImmutable(self::stringValue($payload, 'occurred_at', 'now')),
            actor: TreatmentPlanActor::fromArray(self::actorPayload($payload['actor'] ?? null)),
            reason: self::nullableStringValue($payload, 'reason'),
        );
    }

    /**
     * @return array{type?: mixed, id?: mixed, name?: mixed}
     */
    private static function actorPayload(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return [
            'type' => $value['type'] ?? null,
            'id' => $value['id'] ?? null,
            'name' => $value['name'] ?? null,
        ];
    }

    /**
     * @param  array{
     *     from_status?: mixed,
     *     to_status?: mixed,
     *     occurred_at?: mixed,
     *     actor?: mixed,
     *     reason?: mixed
     * }  $payload
     */
    private static function nullableStringValue(array $payload, string $key): ?string
    {
        /** @var mixed $value */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array{
     *     from_status?: mixed,
     *     to_status?: mixed,
     *     occurred_at?: mixed,
     *     actor?: mixed,
     *     reason?: mixed
     * }  $payload
     */
    private static function stringValue(array $payload, string $key, string $fallback = ''): string
    {
        return self::nullableStringValue($payload, $key) ?? $fallback;
    }
}
