<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\ResetPasswordCommand;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Contracts\PasswordResetManager;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ResetPasswordCommandHandler
{
    public function __construct(
        private readonly PasswordResetManager $passwordResetManager,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(ResetPasswordCommand $command): void
    {
        $user = $this->passwordResetManager->reset($command->email, $command->token, $command->password);

        if ($user === null) {
            throw ValidationException::withMessages([
                'token' => ['The password reset token is invalid or expired.'],
            ]);
        }

        $revokedSessions = $this->authSessionRepository->revokeAllForUser($user->id, CarbonImmutable::now());
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.password_reset_completed',
            objectType: 'user',
            objectId: $user->id,
            metadata: [
                'revoked_sessions' => $revokedSessions,
            ],
        ));
    }
}
