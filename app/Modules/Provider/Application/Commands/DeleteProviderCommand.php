<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class DeleteProviderCommand
{
    public function __construct(
        public string $providerId,
    ) {}
}
