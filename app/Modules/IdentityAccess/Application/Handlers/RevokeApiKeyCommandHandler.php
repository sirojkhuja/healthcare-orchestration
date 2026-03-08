<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\RevokeApiKeyCommand;
use App\Modules\IdentityAccess\Application\Contracts\ApiKeyRepository;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RevokeApiKeyCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(RevokeApiKeyCommand $command): void
    {
        $current = $this->authenticatedRequestContext->current();
        $revokedAt = CarbonImmutable::now();
        $revoked = $this->apiKeyRepository->revokeForUser($command->keyId, $current->user->id, $revokedAt);

        if (! $revoked) {
            throw new NotFoundHttpException('The requested API key does not exist.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.api_key_revoked',
            objectType: 'api_key',
            objectId: $command->keyId,
            after: [
                'status' => 'revoked',
            ],
            metadata: [
                'actor_user_id' => $current->user->id,
                'auth_session_id' => $current->sessionId,
                'revoked_at' => $revokedAt->format(DATE_ATOM),
            ],
        ));
    }
}
