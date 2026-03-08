<?php

namespace Tests\Fixtures\Messaging;

use App\Shared\Application\Contracts\KafkaProducer;
use App\Shared\Application\Data\OutboxMessage;
use RuntimeException;

final class FakeKafkaProducer implements KafkaProducer
{
    /** @var list<OutboxMessage> */
    public array $published = [];

    public int $failuresRemaining = 0;

    #[\Override]
    public function publish(OutboxMessage $message): void
    {
        if ($this->failuresRemaining > 0) {
            $this->failuresRemaining--;

            throw new RuntimeException('Kafka transport failure.');
        }

        $this->published[] = $message;
    }
}
