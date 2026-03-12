<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class EmailProviderSettingsData
{
    public function __construct(
        public string $tenantId,
        public bool $enabled,
        public string $providerKey,
        public string $fromAddress,
        public string $fromName,
        public ?string $replyToAddress,
        public ?string $replyToName,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'enabled' => $this->enabled,
            'provider_key' => $this->providerKey,
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
            'reply_to_address' => $this->replyToAddress,
            'reply_to_name' => $this->replyToName,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
