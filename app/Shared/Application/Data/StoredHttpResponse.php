<?php

namespace App\Shared\Application\Data;

final readonly class StoredHttpResponse
{
    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers,
    ) {}
}
