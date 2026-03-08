<?php

namespace App\Shared\Application\Services;

use App\Shared\Application\Contracts\ConsumerReceiptStore;
use App\Shared\Application\Contracts\KafkaConsumerHandler;
use App\Shared\Application\Data\ConsumedKafkaMessage;
use App\Shared\Application\Data\KafkaConsumerDispatchResult;

final class IdempotentKafkaConsumerBus
{
    public function __construct(private readonly ConsumerReceiptStore $consumerReceiptStore) {}

    public function dispatch(KafkaConsumerHandler $handler, ConsumedKafkaMessage $message): KafkaConsumerDispatchResult
    {
        if ($this->consumerReceiptStore->hasProcessed($handler->consumerName(), $message->messageId)) {
            return KafkaConsumerDispatchResult::skipped();
        }

        $handler->handle($message);
        $this->consumerReceiptStore->recordProcessed(
            consumerName: $handler->consumerName(),
            messageId: $message->messageId,
            topic: $message->topic,
            partition: $message->partition,
        );

        return KafkaConsumerDispatchResult::processed();
    }
}
