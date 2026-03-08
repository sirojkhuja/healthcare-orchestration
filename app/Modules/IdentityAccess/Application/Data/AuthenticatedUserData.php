<?php

namespace App\Modules\IdentityAccess\Application\Data;

final readonly class AuthenticatedUserData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}

    /**
     * @return array{id: string, name: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
