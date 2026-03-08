<?php

namespace App\Modules\AuditCompliance\Application\Data;

final readonly class AuditActor
{
    public function __construct(
        public string $type,
        public ?string $id,
        public ?string $name,
    ) {}

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
     * @param  array{type: string, id?: string|null, name?: string|null}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            type: $payload['type'],
            id: $payload['id'] ?? null,
            name: $payload['name'] ?? null,
        );
    }
}
