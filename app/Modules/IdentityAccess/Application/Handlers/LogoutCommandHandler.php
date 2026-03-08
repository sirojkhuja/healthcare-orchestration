<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\LogoutCommand;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use Carbon\CarbonImmutable;

final class LogoutCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(LogoutCommand $command): void
    {
        $current = $this->authenticatedRequestContext->current();
        $revokedAt = CarbonImmutable::now();

        $this->authSessionRepository->revoke($current->sessionId, $revokedAt);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.logout',
            objectType: 'auth_session',
            objectId: $current->sessionId,
            before: [
                'status' => 'active',
            ],
            after: [
                'status' => 'revoked',
            ],
            metadata: [
                'user_id' => $current->user->id,
                'ip_address' => $command->ipAddress,
                'user_agent' => $command->userAgent,
                'revoked_at' => $revokedAt->toIso8601String(),
            ],
        ));
    }
}
