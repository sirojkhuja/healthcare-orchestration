<?php

namespace App\Modules\IdentityAccess\Application\Handlers;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Commands\LoginCommand;
use App\Modules\IdentityAccess\Application\Contracts\AccessTokenService;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Data\AuthenticatedSessionData;
use App\Modules\IdentityAccess\Application\Data\AuthTokensData;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;

final class LoginCommandHandler
{
    public function __construct(
        private readonly IdentityUserProvider $identityUserProvider,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly AccessTokenService $accessTokenService,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function handle(LoginCommand $command): AuthenticatedSessionData
    {
        $user = $this->identityUserProvider->attempt($command->email, $command->password);

        if ($user === null) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $now = CarbonImmutable::now();
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
}
