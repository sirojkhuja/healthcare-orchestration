<?php

namespace App\Modules\Treatment\Domain\TreatmentPlans;

use InvalidArgumentException;

final readonly class TreatmentPlanActor
{
    public function __construct(
        public string $type,
        public ?string $id,
        public ?string $name,
    ) {
        if (trim($this->type) === '') {
            throw new InvalidArgumentException('Treatment plan actor type is required.');
        }
    }

    /**
     * @return array{type: string, id: string|null, name: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    /**
     * @param  array{type?: mixed, id?: mixed, name?: mixed}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            type: self::stringValue($payload, 'type'),
            id: self::nullableStringValue($payload, 'id'),
            name: self::nullableStringValue($payload, 'name'),
        );
    }

    /**
     * @param  array{type?: mixed, id?: mixed, name?: mixed}  $payload
     */
    private static function nullableStringValue(array $payload, string $key): ?string
    {
        /** @var mixed $value */
        $value = $payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array{type?: mixed, id?: mixed, name?: mixed}  $payload
     */
    private static function stringValue(array $payload, string $key): string
    {
        return self::nullableStringValue($payload, $key) ?? '';
    }
}
