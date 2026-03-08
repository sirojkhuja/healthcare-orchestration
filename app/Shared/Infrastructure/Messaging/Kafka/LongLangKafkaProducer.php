<?php

namespace App\Shared\Infrastructure\Messaging\Kafka;

use App\Shared\Application\Contracts\KafkaProducer;
use App\Shared\Application\Data\OutboxMessage;
use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;
use longlang\phpkafka\Protocol\RecordBatch\RecordHeader;

final class LongLangKafkaProducer implements KafkaProducer
{
    #[\Override]
    public function publish(OutboxMessage $message): void
    {
        $producer = new Producer($this->producerConfig());

        try {
            $producer->send(
                topic: $message->topic,
                value: json_encode($message->payload, JSON_THROW_ON_ERROR),
                key: $message->partitionKey ?? $message->eventId,
                headers: $this->headers($message),
            );
        } finally {
            $producer->close();
        }
    }

    /**
     * @return list<RecordHeader>
     */
    private function headers(OutboxMessage $message): array
    {
        $headers = array_filter([
            'event_id' => $message->eventId,
            'event_type' => $message->eventType,
            'tenant_id' => $message->tenantId,
            'request_id' => $message->requestId,
            'correlation_id' => $message->correlationId,
            'causation_id' => $message->causationId,
            ...$message->headers,
        ], static fn (?string $value): bool => is_string($value) && $value !== '');

        return array_map(
            static fn (string $key, string $value): RecordHeader => (new RecordHeader)
                ->setHeaderKey($key)
                ->setValue($value),
            array_keys($headers),
            array_values($headers),
        );
    }

    private function producerConfig(): ProducerConfig
    {
        $config = new ProducerConfig;
        $config->setBootstrapServers(config()->string('medflow.kafka.brokers', 'kafka:9092'));
        $config->setClientId(config()->string('medflow.kafka.client_id', 'medflow-app'));
        $config->setAcks(config()->integer('medflow.kafka.acks', 1));
        $config->setProduceRetry(config()->integer('medflow.kafka.transport.produce_retry', 3));
        $config->setProduceRetrySleep(config()->float('medflow.kafka.transport.produce_retry_sleep', 0.1));

        return $config;
    }
}
