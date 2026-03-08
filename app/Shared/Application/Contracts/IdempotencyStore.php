<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\IdempotencyDecision;
use App\Shared\Application\Data\IdempotencyScope;
use App\Shared\Application\Data\StoredHttpResponse;
use DateTimeInterface;

interface IdempotencyStore
{
    public function acquire(IdempotencyScope $scope, string $key, string $fingerprint, DateTimeInterface $expiresAt): IdempotencyDecision;

    public function complete(string $recordId, StoredHttpResponse $response): void;

    public function release(string $recordId): void;
}
