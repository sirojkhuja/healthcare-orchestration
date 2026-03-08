<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\OutboxMessage;

interface KafkaProducer
{
    public function publish(OutboxMessage $message): void;
}
