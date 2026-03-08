<?php

namespace App\Shared\Infrastructure\Messaging\Persistence;

use App\Shared\Application\Contracts\ConsumerReceiptStore;
use Illuminate\Support\Str;

final class DatabaseConsumerReceiptStore implements ConsumerReceiptStore
{
    #[\Override]
    public function hasProcessed(string $consumerName, string $messageId): bool
    {
        return ConsumerReceiptRecord::query()
            ->where('consumer_name', $consumerName)
            ->where('message_id', $messageId)
            ->exists();
    }

    #[\Override]
    public function recordProcessed(string $consumerName, string $messageId, string $topic, int $partition): void
    {
        ConsumerReceiptRecord::query()->create([
            'id' => Str::uuid()->toString(),
            'consumer_name' => $consumerName,
            'message_id' => $messageId,
            'topic' => $topic,
            'partition' => $partition,
            'processed_at' => now(),
        ]);
    }
}
