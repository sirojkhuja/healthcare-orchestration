<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class AuthenticatedRequestData
{
    public function __construct(
        public AuthenticatedUserData $user,
        public string $sessionId,
    ) {}

    /**
     * @return array{
     *     user: array{id: string, name: string, email: string},
     *     session: array{id: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'session' => [
                'id' => $this->sessionId,
            ],
        ];
    }
}
