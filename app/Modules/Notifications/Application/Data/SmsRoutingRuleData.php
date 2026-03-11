<?php

namespace App\Modules\Notifications\Application\Data;

final readonly class SmsRoutingRuleData
{
    /**
     * @param  list<string>  $providers
     */
    public function __construct(
        public string $tenantId,
        public string $messageType,
        public array $providers,
        public string $source = 'custom',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message_type' => $this->messageType,
            'providers' => $this->providers,
            'source' => $this->source,
        ];
    }
}
