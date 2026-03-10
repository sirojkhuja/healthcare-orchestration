<?php

namespace App\Modules\Scheduling\Domain\Appointments;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AppointmentSlot
{
    public function __construct(
        public DateTimeImmutable $startAt,
        public DateTimeImmutable $endAt,
        public string $timezone,
    ) {
        if ($this->endAt <= $this->startAt) {
            throw new InvalidArgumentException('Appointment slot end time must be after the start time.');
        }

        if (trim($this->timezone) === '') {
            throw new InvalidArgumentException('Appointment slot timezone is required.');
        }
    }

    public function hasEndedAt(DateTimeImmutable $moment): bool
    {
        return $this->endAt <= $moment;
    }

    public function hasStartedAt(DateTimeImmutable $moment): bool
    {
        return $this->startAt <= $moment;
    }

    /**
     * @return array{start_at: string, end_at: string, timezone: string}
     */
    public function toArray(): array
    {
        return [
            'start_at' => $this->startAt->format(DATE_ATOM),
            'end_at' => $this->endAt->format(DATE_ATOM),
            'timezone' => $this->timezone,
        ];
    }

    /**
     * @param  array{start_at?: mixed, end_at?: mixed, timezone?: mixed}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            startAt: new DateTimeImmutable(self::stringValue($payload, 'start_at', 'now')),
            endAt: new DateTimeImmutable(self::stringValue($payload, 'end_at', 'now')),
            timezone: self::stringValue($payload, 'timezone'),
        );
    }

    /**
     * @param  array{start_at?: mixed, end_at?: mixed, timezone?: mixed}  $payload
     */
    private static function stringValue(array $payload, string $key, string $fallback = ''): string
    {
        /** @var mixed $value */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : $fallback;
    }
}
