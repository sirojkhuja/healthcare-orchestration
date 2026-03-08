<?php

namespace Tests\Fixtures\Messaging;

use App\Shared\Application\Contracts\KafkaConsumerHandler;
use App\Shared\Application\Data\ConsumedKafkaMessage;

final class FakeKafkaConsumerHandler implements KafkaConsumerHandler
{
    /** @var list<ConsumedKafkaMessage> */
    public array $messages = [];

    #[\Override]
    public function consumerGroup(): string
    {
        return 'testing.consumer-group';
    }

    #[\Override]
    public function consumerName(): string
    {
        return 'testing.fake-consumer';
    }

    #[\Override]
    public function handle(ConsumedKafkaMessage $message): void
    {
        $this->messages[] = $message;
    }

    #[\Override]
    public function topics(): array
    {
        return ['medflow.testing.v1'];
    }
}
