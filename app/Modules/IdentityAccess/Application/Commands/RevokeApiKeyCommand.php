<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class RevokeApiKeyCommand
{
    public function __construct(
        public string $keyId,
    ) {}
}
