<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class ClickWebhookResponseData
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public array $body,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->body;
    }
}
