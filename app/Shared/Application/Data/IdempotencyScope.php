<?php

namespace App\Shared\Application\Data;

final readonly class IdempotencyScope
{
    public function __construct(
        public string $operation,
        public ?string $tenantId,
        public ?string $actorId,
    ) {}

    public function hash(): string
    {
        return hash('sha256', implode('|', [
            $this->operation,
            $this->tenantId ?? 'global',
            $this->actorId ?? 'anonymous',
        ]));
    }
}
