<?php

namespace App\Modules\Notifications\Infrastructure\Integrations;

use App\Modules\Notifications\Application\Contracts\SmsProvider;
use App\Modules\Notifications\Application\Data\SmsDeliveryAttemptData;
use App\Modules\Notifications\Application\Data\SmsDeliveryRequestData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

abstract class AbstractStubSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly string $providerKey,
        private readonly string $providerName,
        private readonly string $sender = 'MedFlow',
        private readonly string $messageIdPrefix = 'sms',
    ) {}

    #[\Override]
    public function providerKey(): string
    {
        return $this->providerKey;
    }

    #[\Override]
    public function providerName(): string
    {
        return $this->providerName;
    }

    #[\Override]
    public function send(SmsDeliveryRequestData $request): SmsDeliveryAttemptData
    {
        return new SmsDeliveryAttemptData(
            providerKey: $this->providerKey,
            providerName: $this->providerName,
            status: 'sent',
            occurredAt: CarbonImmutable::now(),
            providerMessageId: sprintf(
                '%s-%s-%s',
                $this->messageIdPrefix,
                strtolower($this->senderSlug()),
                Str::uuid()->toString(),
            ),
        );
    }

    private function senderSlug(): string
    {
        $sender = trim($this->sender);

        return $sender === '' ? 'medflow' : Str::slug($sender, '-');
    }
}
