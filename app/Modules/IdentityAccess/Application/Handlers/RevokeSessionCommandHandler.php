<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\RevokeSessionCommand;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RevokeSessionCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(RevokeSessionCommand $command): void
    {
        $current = $this->authenticatedRequestContext->current();
        $revokedAt = CarbonImmutable::now();
        $revoked = $this->authSessionRepository->revokeForUser($command->sessionId, $current->user->id, $revokedAt);

        if (! $revoked) {
            throw new NotFoundHttpException('The requested auth session does not exist.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.session_revoked',
            objectType: 'auth_session',
            objectId: $command->sessionId,
            after: [
                'status' => 'revoked',
            ],
            metadata: [
                'actor_user_id' => $current->user->id,
                'revoked_at' => $revokedAt->toIso8601String(),
            ],
        ));
    }
}
