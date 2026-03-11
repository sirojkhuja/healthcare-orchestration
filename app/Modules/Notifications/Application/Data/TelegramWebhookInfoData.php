<?php

namespace App\Modules\Notifications\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TelegramWebhookInfoData
{
    public function __construct(
        public string $url,
        public bool $hasCustomCertificate,
        public int $pendingUpdateCount,
        public ?CarbonImmutable $lastErrorDate = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'has_custom_certificate' => $this->hasCustomCertificate,
            'pending_update_count' => $this->pendingUpdateCount,
            'last_error_date' => $this->lastErrorDate?->toIso8601String(),
        ];
    }
}
