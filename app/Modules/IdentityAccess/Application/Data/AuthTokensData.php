<?php

namespace App\Modules\IdentityAccess\Application\Data;

use DateTimeInterface;

final readonly class AuthTokensData
{
    public function __construct(
        public string $accessToken,
        public DateTimeInterface $accessTokenExpiresAt,
        public string $refreshToken,
        public DateTimeInterface $refreshTokenExpiresAt,
        public string $tokenType = 'Bearer',
    ) {}

    /**
     * @return array{
     *     token_type: string,
     *     access_token: string,
     *     access_token_expires_at: string,
     *     refresh_token: string,
     *     refresh_token_expires_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'token_type' => $this->tokenType,
            'access_token' => $this->accessToken,
            'access_token_expires_at' => $this->accessTokenExpiresAt->format(DATE_ATOM),
            'refresh_token' => $this->refreshToken,
            'refresh_token_expires_at' => $this->refreshTokenExpiresAt->format(DATE_ATOM),
        ];
    }
}
