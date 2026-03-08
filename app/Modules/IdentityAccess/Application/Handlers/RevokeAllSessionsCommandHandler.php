<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\RevokeAllSessionsCommand;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use Carbon\CarbonImmutable;

final class RevokeAllSessionsCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(RevokeAllSessionsCommand $command): int
    {
        $current = $this->authenticatedRequestContext->current();
        $revokedAt = CarbonImmutable::now();
        $revoked = $this->authSessionRepository->revokeAllForUser($current->user->id, $revokedAt);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.sessions_revoked_all',
            objectType: 'user',
            objectId: $current->user->id,
            metadata: [
                'revoked_sessions' => $revoked,
                'revoked_at' => $revokedAt->toIso8601String(),
            ],
        ));

        return $revoked;
    }
}
