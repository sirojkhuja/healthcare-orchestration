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

    /**
     * @param  array{
     *     from_status?: mixed,
     *     to_status?: mixed,
     *     occurred_at?: mixed,
     *     actor?: mixed,
     *     reason?: mixed,
     *     admin_override?: mixed,
     *     restored_from_status?: mixed,
     *     replacement_appointment_id?: mixed,
     *     replacement_slot?: mixed
     * }  $payload
     */
    public static function fromArray(array $payload): self
    {
        $restoredFromStatus = self::nullableStringValue($payload, 'restored_from_status');
        $replacementSlotPayload = self::slotPayload($payload['replacement_slot'] ?? null);

        return new self(
            fromStatus: AppointmentStatus::from(self::stringValue($payload, 'from_status', AppointmentStatus::DRAFT->value)),
            toStatus: AppointmentStatus::from(self::stringValue($payload, 'to_status', AppointmentStatus::DRAFT->value)),
            occurredAt: new DateTimeImmutable(self::stringValue($payload, 'occurred_at', 'now')),
            actor: AppointmentActor::fromArray(self::actorPayload($payload['actor'] ?? null)),
            reason: self::nullableStringValue($payload, 'reason'),
            adminOverride: self::boolValue($payload, 'admin_override'),
            restoredFromStatus: $restoredFromStatus !== null
                ? AppointmentStatus::from($restoredFromStatus)
                : null,
            replacementAppointmentId: self::nullableStringValue($payload, 'replacement_appointment_id'),
            replacementSlot: $replacementSlotPayload !== []
                ? AppointmentSlot::fromArray($replacementSlotPayload)
                : null,
        );
    }

    /**
     * @param  array{
     *     from_status?: mixed,
     *     to_status?: mixed,
     *     occurred_at?: mixed,
     *     actor?: mixed,
     *     reason?: mixed,
     *     admin_override?: mixed,
     *     restored_from_status?: mixed,
     *     replacement_appointment_id?: mixed,
     *     replacement_slot?: mixed
     * }  $payload
     */
    private static function boolValue(array $payload, string $key): bool
    {
        /** @var mixed $value */
        $value = $payload[$key] ?? false;

        return is_bool($value) ? $value : (bool) $value;
    }

    /**
     * @return array{id?: mixed, name?: mixed, type?: mixed}
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
     *     reason?: mixed,
     *     admin_override?: mixed,
     *     restored_from_status?: mixed,
     *     replacement_appointment_id?: mixed,
     *     replacement_slot?: mixed
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
     *     reason?: mixed,
     *     admin_override?: mixed,
     *     restored_from_status?: mixed,
     *     replacement_appointment_id?: mixed,
     *     replacement_slot?: mixed
     * }  $payload
     */
    private static function stringValue(array $payload, string $key, string $fallback = ''): string
    {
        return self::nullableStringValue($payload, $key) ?? $fallback;
    }

    /**
     * @return array{start_at?: mixed, end_at?: mixed, timezone?: mixed}
     */
    private static function slotPayload(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return [
            'start_at' => $value['start_at'] ?? null,
            'end_at' => $value['end_at'] ?? null,
            'timezone' => $value['timezone'] ?? null,
        ];
    }
}
