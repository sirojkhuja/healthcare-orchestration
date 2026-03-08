<?php

namespace App\Shared\Application\Data;

final readonly class IdempotencyDecision
{
    private function __construct(
        public bool $shouldExecute,
        public ?string $recordId,
        public ?StoredHttpResponse $storedResponse,
    ) {}

    public static function execute(string $recordId): self
    {
        return new self(
            shouldExecute: true,
            recordId: $recordId,
            storedResponse: null,
        );
    }

    public static function replay(StoredHttpResponse $storedResponse): self
    {
        return new self(
            shouldExecute: false,
            recordId: null,
            storedResponse: $storedResponse,
        );
    }

    public function isReplay(): bool
    {
        return ! $this->shouldExecute;
    }
}
