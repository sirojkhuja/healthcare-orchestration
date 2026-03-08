<?php

namespace App\Shared\Application\Contracts;

interface ConsumerReceiptStore
{
    public function hasProcessed(string $consumerName, string $messageId): bool;

    public function recordProcessed(string $consumerName, string $messageId, string $topic, int $partition): void;
}
