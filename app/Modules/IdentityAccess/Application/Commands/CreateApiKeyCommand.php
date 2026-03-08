<?php

namespace App\Modules\IdentityAccess\Application\Commands;

final readonly class CreateApiKeyCommand
{
    public function __construct(
        public string $name,
        public ?string $expiresAt = null,
    ) {}
}
