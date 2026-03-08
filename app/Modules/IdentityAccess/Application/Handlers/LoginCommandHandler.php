<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Contracts\SecurityEventWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\AuditCompliance\Application\Data\SecurityEventInput;
use App\Modules\IdentityAccess\Application\Commands\LoginCommand;
use App\Modules\IdentityAccess\Application\Contracts\AccessTokenService;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Contracts\MfaChallengeRepository;
use App\Modules\IdentityAccess\Application\Contracts\MfaCredentialRepository;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedSessionData;
use App\Modules\IdentityAccess\Application\Data\AuthTokensData;
use App\Modules\IdentityAccess\Application\Exceptions\MfaChallengeRequiredException;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;

final class LoginCommandHandler
{
    public function __construct(
        private readonly IdentityUserProvider $identityUserProvider,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly AccessTokenService $accessTokenService,
        private readonly MfaCredentialRepository $mfaCredentialRepository,
        private readonly MfaChallengeRepository $mfaChallengeRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly SecurityEventWriter $securityEventWriter,
    ) {}

    public function handle(LoginCommand $command): AuthenticatedSessionData
    {
        $user = $this->identityUserProvider->attempt($command->email, $command->password);

        if ($user === null) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $now = CarbonImmutable::now();

        if ($this->mfaCredentialRepository->findEnabledForUser($user->id) !== null) {
            $challenge = $this->mfaChallengeRepository->create(
                userId: $user->id,
                expiresAt: $now->addMinutes($this->mfaChallengeTtlMinutes()),
                ipAddress: $command->ipAddress,
                userAgent: $command->userAgent,
            );

            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'auth.mfa.challenge_required',
                objectType: 'mfa_challenge',
                objectId: $challenge->challengeId,
                metadata: [
                    'user_id' => $user->id,
                    'expires_at' => $challenge->expiresAt->format(DATE_ATOM),
                    'ip_address' => $command->ipAddress,
                    'user_agent' => $command->userAgent,
                ],
            ));
            $this->securityEventWriter->record(new SecurityEventInput(
                eventType: 'mfa.challenge_required',
                subjectType: 'mfa_challenge',
                subjectId: $challenge->challengeId,
                userId: $user->id,
                metadata: [
                    'expires_at' => $challenge->expiresAt->format(DATE_ATOM),
                ],
            ));

            throw new MfaChallengeRequiredException($challenge->challengeId, $challenge->expiresAt);
        }

        $refreshToken = $this->refreshToken();
        $session = $this->authSessionRepository->create(
            userId: $user->id,
            refreshToken: $refreshToken,
            accessTokenExpiresAt: $now->addMinutes($this->accessTokenTtlMinutes()),
            refreshTokenExpiresAt: $now->addDays($this->refreshTokenTtlDays()),
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
        );

        $tokens = new AuthTokensData(
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
        );

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
            ],
        ));

        return new AuthenticatedSessionData(
            user: $user,
            tokens: $tokens,
            sessionId: $session->sessionId,
        );
    }

    private function accessTokenTtlMinutes(): int
    {
        return config()->integer('medflow.auth.access_token_ttl_minutes', 15);
    }

    private function refreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function refreshTokenTtlDays(): int
    {
        return config()->integer('medflow.auth.refresh_token_ttl_days', 30);
    }

    private function mfaChallengeTtlMinutes(): int
    {
        return config()->integer('medflow.auth.mfa.challenge_ttl_minutes', 10);
    }
}
