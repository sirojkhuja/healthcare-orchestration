<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\ConsumedKafkaMessage;

interface KafkaConsumerHandler
{
    public function consumerName(): string;

    public function consumerGroup(): string;

    /**
     * @return list<string>
     */
    public function topics(): array;

    public function handle(ConsumedKafkaMessage $message): void;
}
