<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\CreateApiKeyCommand;
use App\Modules\IdentityAccess\Application\Contracts\ApiKeyRepository;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Data\CreatedApiKeyData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class CreateApiKeyCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(CreateApiKeyCommand $command): CreatedApiKeyData
    {
        $current = $this->authenticatedRequestContext->current();
        $keyId = Str::uuid()->toString();
        $plainTextKey = sprintf(
            '%s_%s.%s',
            $this->apiKeyPrefix(),
            $keyId,
            bin2hex(random_bytes($this->apiKeySecretBytes())),
        );

        $apiKey = $this->apiKeyRepository->create(
            keyId: $keyId,
            userId: $current->user->id,
            name: $command->name,
            prefix: $this->displayPrefix($keyId),
            tokenHash: hash('sha256', $plainTextKey),
            expiresAt: $this->parseExpiry($command->expiresAt),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.api_key_created',
            objectType: 'api_key',
            objectId: $apiKey->keyId,
            after: [
                'name' => $apiKey->name,
                'status' => 'active',
                'expires_at' => $apiKey->expiresAt?->format(DATE_ATOM),
            ],
            metadata: [
                'actor_user_id' => $current->user->id,
                'auth_session_id' => $current->sessionId,
                'prefix' => $apiKey->prefix,
            ],
        ));

        return new CreatedApiKeyData(
            apiKey: $apiKey->toView(),
            plainTextKey: $plainTextKey,
        );
    }

    private function apiKeyPrefix(): string
    {
        return config()->string('medflow.auth.api_keys.prefix', 'mfk');
    }

    /**
     * @return int<16, max>
     */
    private function apiKeySecretBytes(): int
    {
        $secretBytes = config()->integer('medflow.auth.api_keys.secret_bytes', 32);

        return $secretBytes >= 16 ? $secretBytes : 32;
    }

    private function displayPrefix(string $keyId): string
    {
        return sprintf('%s_%s', $this->apiKeyPrefix(), substr($keyId, 0, 8));
    }

    private function parseExpiry(?string $expiresAt): ?CarbonImmutable
    {
        if (! is_string($expiresAt) || $expiresAt === '') {
            return null;
        }

        return CarbonImmutable::parse($expiresAt);
    }
}
