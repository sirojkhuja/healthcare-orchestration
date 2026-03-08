<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\AccessTokenService;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use UnexpectedValueException;

final class JwtRequestAuthenticator
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly AuthSessionRepository $authSessionRepository,
    ) {}

    public function authenticate(Request $request): ?Authenticatable
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = $this->accessTokenService->decode($token);
        } catch (UnexpectedValueException) {
            return null;
        }

        $session = $this->authSessionRepository->findActiveByAccessToken(
            sessionId: $payload->sessionId,
            accessTokenId: $payload->accessTokenId,
            now: CarbonImmutable::now(),
        );

        if ($session === null) {
            return null;
        }

        $user = User::query()->find($payload->userId);

        if (! $user instanceof User) {
            return null;
        }

        $this->authSessionRepository->touchUsage($session->sessionId, CarbonImmutable::now());
        $request->attributes->set('auth_session_id', $session->sessionId);

        return $user;
    }
}
