<?php

namespace App\Shared\Application\Data;

final readonly class ConsumedKafkaMessage
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $messageId,
        public string $topic,
        public int $partition,
        public ?string $key,
        public array $headers,
        public mixed $payload,
    ) {}
}
