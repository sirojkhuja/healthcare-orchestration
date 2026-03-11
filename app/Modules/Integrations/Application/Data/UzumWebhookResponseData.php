<?php

namespace App\Modules\Integrations\Application\Data;

final readonly class UzumWebhookResponseData
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public array $response,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->response;
    }
}
