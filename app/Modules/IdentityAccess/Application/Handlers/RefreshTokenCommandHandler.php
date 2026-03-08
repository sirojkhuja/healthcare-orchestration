<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\RefreshTokenCommand;
use App\Modules\IdentityAccess\Application\Contracts\AccessTokenService;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedSessionData;
use App\Modules\IdentityAccess\Application\Data\AuthTokensData;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;

final class RefreshTokenCommandHandler
{
    public function __construct(
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly IdentityUserProvider $identityUserProvider,
        private readonly AccessTokenService $accessTokenService,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(RefreshTokenCommand $command): AuthenticatedSessionData
    {
        $now = CarbonImmutable::now();
        $session = $this->authSessionRepository->findActiveByRefreshToken($command->refreshToken, $now);

        if ($session === null) {
            throw new AuthenticationException('Refresh token is invalid or expired.');
        }

        $user = $this->identityUserProvider->findById($session->userId);

        if ($user === null) {
            throw new AuthenticationException('Refresh token subject no longer exists.');
        }

        $refreshToken = $this->refreshToken();
        $rotatedSession = $this->authSessionRepository->rotateRefreshToken(
            sessionId: $session->sessionId,
            refreshToken: $refreshToken,
            accessTokenExpiresAt: $now->addMinutes($this->accessTokenTtlMinutes()),
            refreshTokenExpiresAt: $now->addDays($this->refreshTokenTtlDays()),
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
            usedAt: $now,
        );

        if ($rotatedSession === null) {
            throw new AuthenticationException('Refresh token is invalid or expired.');
        }

        $tokens = new AuthTokensData(
            accessToken: $this->accessTokenService->issue(
                user: $user,
                sessionId: $rotatedSession->sessionId,
                accessTokenId: $rotatedSession->accessTokenId,
                issuedAt: $now,
                expiresAt: $rotatedSession->accessTokenExpiresAt,
            ),
            accessTokenExpiresAt: $rotatedSession->accessTokenExpiresAt,
            refreshToken: $refreshToken,
            refreshTokenExpiresAt: $rotatedSession->refreshTokenExpiresAt,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'auth.refresh',
            objectType: 'auth_session',
            objectId: $rotatedSession->sessionId,
            before: [
                'refresh_token_expires_at' => $session->refreshTokenExpiresAt->format(DATE_ATOM),
            ],
            after: [
                'refresh_token_expires_at' => $rotatedSession->refreshTokenExpiresAt->format(DATE_ATOM),
            ],
            metadata: [
                'user_id' => $user->id,
                'ip_address' => $command->ipAddress,
                'user_agent' => $command->userAgent,
            ],
        ));

        return new AuthenticatedSessionData(
            user: $user,
            tokens: $tokens,
            sessionId: $rotatedSession->sessionId,
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
}
