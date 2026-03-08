<?php

namespace App\Shared\Infrastructure\Messaging\Kafka;

use App\Shared\Application\Contracts\KafkaConsumerHandler;
use App\Shared\Application\Data\ConsumedKafkaMessage;
use App\Shared\Application\Services\IdempotentKafkaConsumerBus;
use LogicException;
use longlang\phpkafka\Consumer\ConsumeMessage;
use longlang\phpkafka\Consumer\Consumer;
use longlang\phpkafka\Consumer\ConsumerConfig;

final class LongLangKafkaConsumerLoop
{
    public function __construct(private readonly IdempotentKafkaConsumerBus $consumerBus) {}

    public function run(KafkaConsumerHandler $handler, int $maxMessages = 0): void
    {
        $consumer = new Consumer($this->consumerConfig($handler));
        $processedMessages = 0;

        try {
            while ($maxMessages === 0 || $processedMessages < $maxMessages) {
                $rawMessage = $consumer->consume();

                if (! $rawMessage instanceof ConsumeMessage) {
                    usleep(100000);

                    continue;
                }

                $this->consumerBus->dispatch($handler, $this->mapMessage($rawMessage));
                $consumer->ack($rawMessage);
                $processedMessages++;
            }
        } finally {
            $consumer->close();
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(ConsumeMessage $message): array
    {
        $headers = [];

        foreach ($message->getHeaders() as $header) {
            $headers[$header->getHeaderKey()] = $header->getValue();
        }

        return $headers;
    }

    private function mapMessage(ConsumeMessage $message): ConsumedKafkaMessage
    {
        $headers = $this->headers($message);
        $messageId = $headers['event_id'] ?? $message->getKey();

        if (! is_string($messageId) || $messageId === '') {
            throw new LogicException('Kafka consumer messages must provide an event identifier header or message key.');
        }

        $payload = $message->getValue();
        if ($payload === null || $payload === '') {
            $decodedPayload = [];
        } else {
            /** @var mixed $decodedPayload */
            $decodedPayload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decodedPayload)) {
                throw new LogicException('Kafka consumer payloads must decode to a JSON object.');
            }
        }

        return new ConsumedKafkaMessage(
            messageId: $messageId,
            topic: $message->getTopic(),
            partition: $message->getPartition(),
            key: $message->getKey(),
            headers: $headers,
            payload: $decodedPayload,
        );
    }

    private function consumerConfig(KafkaConsumerHandler $handler): ConsumerConfig
    {
        $config = new ConsumerConfig;
        $config->setBootstrapServers(config()->string('medflow.kafka.brokers', 'kafka:9092'));
        $config->setClientId(config()->string('medflow.kafka.client_id', 'medflow-app'));
        $config->setGroupId($handler->consumerGroup());
        $config->setTopic($handler->topics());
        $config->setAutoCommit(false);
        $config->setInterval(config()->float('medflow.kafka.consumer.poll_interval_seconds', 0.1));
        $config->setGroupRetry(config()->integer('medflow.kafka.consumer.group_retry', 5));
        $config->setGroupRetrySleep(config()->float('medflow.kafka.consumer.group_retry_sleep', 1.0));

        return $config;
    }
}
