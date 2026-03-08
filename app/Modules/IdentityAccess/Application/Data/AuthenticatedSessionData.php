<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class AuthenticatedSessionData
{
    public function __construct(
        public AuthenticatedUserData $user,
        public AuthTokensData $tokens,
        public string $sessionId,
    ) {}

    /**
     * @return array{
     *     user: array{id: string, name: string, email: string},
     *     session: array{id: string},
     *     tokens: array{
     *         token_type: string,
     *         access_token: string,
     *         access_token_expires_at: string,
     *         refresh_token: string,
     *         refresh_token_expires_at: string
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'session' => [
                'id' => $this->sessionId,
            ],
            'tokens' => $this->tokens->toArray(),
        ];
    }
}
