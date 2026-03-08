<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Contracts\SecurityEventWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\AuditCompliance\Application\Data\SecurityEventInput;
use App\Modules\IdentityAccess\Application\Commands\SetupMfaCommand;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\MfaCredentialRepository;
use App\Modules\IdentityAccess\Application\Contracts\MfaTotpService;
use App\Modules\IdentityAccess\Application\Data\MfaSetupData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SetupMfaCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly MfaCredentialRepository $mfaCredentialRepository,
        private readonly MfaTotpService $mfaTotpService,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly SecurityEventWriter $securityEventWriter,
    ) {}

    public function handle(SetupMfaCommand $command): MfaSetupData
    {
        $current = $this->authenticatedRequestContext->current();

        if ($this->mfaCredentialRepository->findEnabledForUser($current->user->id) !== null) {
            throw new ConflictHttpException('MFA is already enabled for this account.');
        }

        $now = CarbonImmutable::now();
        $secret = $this->mfaTotpService->generateSecret();
        $recoveryCodes = $this->mfaTotpService->generateRecoveryCodes();
        $credential = $this->mfaCredentialRepository->upsertPending(
            userId: $current->user->id,
            secret: $secret,
            recoveryCodeHashes: array_map(
                fn (string $recoveryCode): string => $this->mfaTotpService->recoveryCodeHash($recoveryCode),
                $recoveryCodes,
            ),
            now: $now,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.mfa.setup_started',
            objectType: 'mfa_credential',
            objectId: $credential->credentialId,
            metadata: [
                'user_id' => $current->user->id,
                'recovery_codes_count' => count($recoveryCodes),
            ],
        ));
        $this->securityEventWriter->record(new SecurityEventInput(
            eventType: 'mfa.setup_started',
            subjectType: 'mfa_credential',
            subjectId: $credential->credentialId,
            userId: $current->user->id,
            metadata: [
                'recovery_codes_count' => count($recoveryCodes),
            ],
        ));

        return new MfaSetupData(
            credentialId: $credential->credentialId,
            secret: $secret,
            otpauthUri: $this->mfaTotpService->provisioningUri($current->user->email, $secret),
            recoveryCodes: $recoveryCodes,
        );
    }
}
