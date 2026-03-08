<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\RequestPasswordResetCommand;
use App\Modules\IdentityAccess\Application\Contracts\PasswordResetManager;

final class RequestPasswordResetCommandHandler
{
    public function __construct(
        private readonly PasswordResetManager $passwordResetManager,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(RequestPasswordResetCommand $command): void
    {
        $this->passwordResetManager->issueToken($command->email);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.password_reset_requested',
            objectType: 'password_reset_request',
            objectId: hash('sha256', strtolower($command->email)),
        ));
    }
}
