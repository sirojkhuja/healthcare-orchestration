<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Contracts\SecurityEventWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\AuditCompliance\Application\Data\SecurityEventInput;
use App\Modules\IdentityAccess\Application\Commands\VerifyMfaCommand;
use App\Modules\IdentityAccess\Application\Contracts\AccessTokenService;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Contracts\MfaChallengeRepository;
use App\Modules\IdentityAccess\Application\Contracts\MfaCredentialRepository;
use App\Modules\IdentityAccess\Application\Contracts\MfaTotpService;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedSessionData;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedUserData;
use App\Modules\IdentityAccess\Application\Data\AuthTokensData;
use App\Modules\IdentityAccess\Application\Data\MfaCredentialData;
use App\Modules\IdentityAccess\Application\Data\MfaVerificationResultData;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class VerifyMfaCommandHandler
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly AccessTokenService $accessTokenService,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly IdentityUserProvider $identityUserProvider,
        private readonly MfaChallengeRepository $mfaChallengeRepository,
        private readonly MfaCredentialRepository $mfaCredentialRepository,
        private readonly MfaTotpService $mfaTotpService,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly SecurityEventWriter $securityEventWriter,
    ) {}

    public function handle(VerifyMfaCommand $command): MfaVerificationResultData
    {
        return is_string($command->challengeId) && $command->challengeId !== ''
            ? $this->verifyLoginChallenge($command)
            : $this->verifySetup($command);
    }

    private function verifyLoginChallenge(VerifyMfaCommand $command): MfaVerificationResultData
    {
        $now = CarbonImmutable::now();
        $challenge = $this->mfaChallengeRepository->findActive($command->challengeId ?? '', $now);

        if ($challenge === null) {
            throw new AuthenticationException('MFA challenge is invalid or expired.');
        }

        $credential = $this->mfaCredentialRepository->findEnabledForUser($challenge->userId);

        if (! $credential instanceof MfaCredentialData || $credential->secret === null) {
            throw new AuthenticationException('MFA is not enabled for this account.');
        }

        $user = $this->identityUserProvider->findById($challenge->userId);

        if (! $user instanceof AuthenticatedUserData) {
            throw new AuthenticationException('MFA challenge subject no longer exists.');
        }

        $usedRecoveryCode = $this->verifyFactor(
            credential: $credential,
            code: $command->code,
            recoveryCode: $command->recoveryCode,
            now: $now,
            challengeId: $challenge->challengeId,
            userId: $challenge->userId,
        );

        if (! $this->mfaChallengeRepository->markVerified($challenge->challengeId, $now)) {
            throw new AuthenticationException('MFA challenge is invalid or expired.');
        }

        if ($usedRecoveryCode) {
            $this->securityEventWriter->record(new SecurityEventInput(
                eventType: 'mfa.recovery_code_used',
                subjectType: 'mfa_credential',
                subjectId: $credential->credentialId,
                userId: $challenge->userId,
            ));
        }

        $session = $this->issueSession($user, $command, $now);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.login',
            objectType: 'auth_session',
            objectId: $session->sessionId,
            after: [
                'user_id' => $user->id,
                'status' => 'active',
            ],
            metadata: [
                'ip_address' => $command->ipAddress,
                'user_agent' => $command->userAgent,
                'mfa_challenge_id' => $challenge->challengeId,
                'used_recovery_code' => $usedRecoveryCode,
            ],
        ));
        $this->securityEventWriter->record(new SecurityEventInput(
            eventType: 'mfa.challenge_verified',
            subjectType: 'mfa_challenge',
            subjectId: $challenge->challengeId,
            userId: $challenge->userId,
            metadata: [
                'used_recovery_code' => $usedRecoveryCode,
                'auth_session_id' => $session->sessionId,
            ],
        ));

        return MfaVerificationResultData::loginCompleted($session);
    }

    private function verifySetup(VerifyMfaCommand $command): MfaVerificationResultData
    {
        $current = $this->authenticatedRequestContext->current();
        $credential = $this->mfaCredentialRepository->findForUser($current->user->id);

        if (! $credential instanceof MfaCredentialData || $credential->secret === null || $credential->disabledAt !== null) {
            throw new ConflictHttpException('MFA setup has not been started for this account.');
        }

        if ($credential->enabledAt !== null) {
            throw new ConflictHttpException('MFA is already enabled for this account.');
        }

        if (! is_string($command->code) || $command->code === '') {
            throw ValidationException::withMessages([
                'code' => ['The MFA code is required.'],
            ]);
        }

        $now = CarbonImmutable::now();

        if (! $this->mfaTotpService->verifyCode($credential->secret, $command->code, $now)) {
            throw ValidationException::withMessages([
                'code' => ['The MFA code is invalid.'],
            ]);
        }

        $enabledCredential = $this->mfaCredentialRepository->enable($credential->credentialId, $now);

        if (! $enabledCredential instanceof MfaCredentialData) {
            throw new ConflictHttpException('MFA setup could not be completed.');
        }

        $this->mfaCredentialRepository->touchLastUsed($enabledCredential->credentialId, $now);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.mfa.enabled',
            objectType: 'mfa_credential',
            objectId: $enabledCredential->credentialId,
            before: [
                'status' => 'pending',
            ],
            after: [
                'status' => 'enabled',
            ],
            metadata: [
                'user_id' => $current->user->id,
            ],
        ));
        $this->securityEventWriter->record(new SecurityEventInput(
            eventType: 'mfa.enabled',
            subjectType: 'mfa_credential',
            subjectId: $enabledCredential->credentialId,
            userId: $current->user->id,
        ));

        return MfaVerificationResultData::setupCompleted();
    }

    private function issueSession(
        AuthenticatedUserData $user,
        VerifyMfaCommand $command,
        CarbonImmutable $now,
    ): AuthenticatedSessionData {
        $refreshToken = bin2hex(random_bytes(32));
        $session = $this->authSessionRepository->create(
            userId: $user->id,
            refreshToken: $refreshToken,
            accessTokenExpiresAt: $now->addMinutes($this->accessTokenTtlMinutes()),
            refreshTokenExpiresAt: $now->addDays($this->refreshTokenTtlDays()),
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
        );

        return new AuthenticatedSessionData(
            user: $user,
            tokens: new AuthTokensData(
                accessToken: $this->accessTokenService->issue(
                    user: $user,
                    sessionId: $session->sessionId,
                    accessTokenId: $session->accessTokenId,
                    issuedAt: $now,
                    expiresAt: $session->accessTokenExpiresAt,
                ),
                accessTokenExpiresAt: $session->accessTokenExpiresAt,
                refreshToken: $refreshToken,
                refreshTokenExpiresAt: $session->refreshTokenExpiresAt,
            ),
            sessionId: $session->sessionId,
        );
    }

    private function verifyFactor(
        MfaCredentialData $credential,
        ?string $code,
        ?string $recoveryCode,
        CarbonImmutable $now,
        string $challengeId,
        string $userId,
    ): bool {
        if (is_string($recoveryCode) && $recoveryCode !== '') {
            return $this->verifyRecoveryCode($credential, $recoveryCode, $now, $challengeId, $userId);
        }

        if (! is_string($code) || $code === '') {
            throw ValidationException::withMessages([
                'code' => ['A TOTP code or recovery code is required.'],
            ]);
        }

        if (! $this->mfaTotpService->verifyCode($credential->secret ?? '', $code, $now)) {
            $this->recordChallengeFailure($challengeId, $userId, 'invalid_code');

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
        string $challengeId,
        string $userId,
    ): bool {
        if (! $this->mfaCredentialRepository->consumeRecoveryCode(
            credentialId: $credential->credentialId,
            recoveryCodeHash: $this->mfaTotpService->recoveryCodeHash($recoveryCode),
            usedAt: $now,
        )) {
            $this->recordChallengeFailure($challengeId, $userId, 'invalid_recovery_code');

            throw ValidationException::withMessages([
                'recovery_code' => ['The recovery code is invalid.'],
            ]);
        }

        return true;
    }

    private function recordChallengeFailure(string $challengeId, string $userId, string $reason): void
    {
        $this->securityEventWriter->record(new SecurityEventInput(
            eventType: 'mfa.challenge_failed',
            subjectType: 'mfa_challenge',
            subjectId: $challengeId,
            userId: $userId,
            metadata: [
                'reason' => $reason,
            ],
        ));
    }

    private function accessTokenTtlMinutes(): int
    {
        return config()->integer('medflow.auth.access_token_ttl_minutes', 15);
    }

    private function refreshTokenTtlDays(): int
    {
        return config()->integer('medflow.auth.refresh_token_ttl_days', 30);
    }
}
