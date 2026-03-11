<?php

namespace Tests\Fixtures\Notifications;

use App\Modules\Notifications\Application\Contracts\SmsProvider;
use App\Modules\Notifications\Application\Data\SmsDeliveryAttemptData;
use App\Modules\Notifications\Application\Data\SmsDeliveryRequestData;
use App\Modules\Notifications\Application\Exceptions\SmsProviderDeliveryException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class FakeSmsProvider implements SmsProvider
{
    /**
     * @var list<array{type: string, message_id?: string, error_code?: string, error_message?: string}>
     */
    private array $plannedResults = [];

    /**
     * @var list<SmsDeliveryRequestData>
     */
    private array $requests = [];

    public function __construct(
        private readonly string $providerKey,
        private readonly string $providerName,
    ) {}

    public function queueFailure(
        string $errorCode = 'temporary_failure',
        string $errorMessage = 'Provider unavailable.',
    ): void {
        $this->plannedResults[] = [
            'type' => 'failure',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];
    }

    public function queueSuccess(?string $messageId = null): void
    {
        $this->plannedResults[] = [
            'type' => 'success',
            'message_id' => $messageId ?? sprintf('%s-%s', $this->providerKey, Str::uuid()->toString()),
        ];
    }

    /**
     * @return list<SmsDeliveryRequestData>
     */
    public function requests(): array
    {
        return $this->requests;
    }

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
        $this->requests[] = $request;
        $planned = array_shift($this->plannedResults);

        if (($planned['type'] ?? null) === 'failure') {
            throw new SmsProviderDeliveryException(
                $planned['error_code'] ?? 'temporary_failure',
                $planned['error_message'] ?? 'Provider unavailable.',
            );
        }

        return new SmsDeliveryAttemptData(
            providerKey: $this->providerKey,
            providerName: $this->providerName,
            status: 'sent',
            occurredAt: CarbonImmutable::now(),
            providerMessageId: $planned['message_id'] ?? sprintf('%s-%s', $this->providerKey, Str::uuid()->toString()),
        );
    }
}
