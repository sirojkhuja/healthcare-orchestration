<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Contracts\SecurityEventWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\AuditCompliance\Application\Data\SecurityEventInput;
use App\Modules\IdentityAccess\Application\Commands\DisableMfaCommand;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\MfaCredentialRepository;
use App\Modules\IdentityAccess\Application\Contracts\MfaTotpService;
use App\Modules\IdentityAccess\Application\Data\MfaCredentialData;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DisableMfaCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly MfaCredentialRepository $mfaCredentialRepository,
        private readonly MfaTotpService $mfaTotpService,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly SecurityEventWriter $securityEventWriter,
    ) {}

    public function handle(DisableMfaCommand $command): void
    {
        $current = $this->authenticatedRequestContext->current();
        $credential = $this->mfaCredentialRepository->findEnabledForUser($current->user->id);

        if (! $credential instanceof MfaCredentialData || $credential->secret === null) {
            throw new ConflictHttpException('MFA is not enabled for this account.');
        }

        $now = CarbonImmutable::now();
        $usedRecoveryCode = $this->verifyFactor($credential, $command, $now);

        if (! $this->mfaCredentialRepository->disable($credential->credentialId, $now)) {
            throw new ConflictHttpException('MFA is not enabled for this account.');
        }

        if ($usedRecoveryCode) {
            $this->securityEventWriter->record(new SecurityEventInput(
                eventType: 'mfa.recovery_code_used',
                subjectType: 'mfa_credential',
                subjectId: $credential->credentialId,
                userId: $current->user->id,
            ));
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.mfa.disabled',
            objectType: 'mfa_credential',
            objectId: $credential->credentialId,
            before: [
                'status' => 'enabled',
            ],
            after: [
                'status' => 'disabled',
            ],
            metadata: [
                'user_id' => $current->user->id,
                'used_recovery_code' => $usedRecoveryCode,
            ],
        ));
        $this->securityEventWriter->record(new SecurityEventInput(
            eventType: 'mfa.disabled',
            subjectType: 'mfa_credential',
            subjectId: $credential->credentialId,
            userId: $current->user->id,
            metadata: [
                'used_recovery_code' => $usedRecoveryCode,
            ],
        ));
    }

    private function verifyFactor(
        MfaCredentialData $credential,
        DisableMfaCommand $command,
        CarbonImmutable $now,
    ): bool {
        if (is_string($command->recoveryCode) && $command->recoveryCode !== '') {
            return $this->verifyRecoveryCode($credential, $command->recoveryCode, $now);
        }

        if (! is_string($command->code) || $command->code === '') {
            throw ValidationException::withMessages([
                'code' => ['A TOTP code or recovery code is required.'],
            ]);
        }

        if (! $this->mfaTotpService->verifyCode($credential->secret ?? '', $command->code, $now)) {
            throw ValidationException::withMessages([
                'code' => ['The MFA code is invalid.'],
            ]);
        }

        $this->mfaCredentialRepository->touchLastUsed($credential->credentialId, $now);

        return false;
    }

    private function verifyRecoveryCode(
        MfaCredentialData $credential,
        string $recoveryCode,
        CarbonImmutable $now,
    ): bool {
        if (! $this->mfaCredentialRepository->consumeRecoveryCode(
            credentialId: $credential->credentialId,
            recoveryCodeHash: $this->mfaTotpService->recoveryCodeHash($recoveryCode),
            usedAt: $now,
        )) {
            throw ValidationException::withMessages([
                'recovery_code' => ['The recovery code is invalid.'],
            ]);
        }

        return true;
    }
}
