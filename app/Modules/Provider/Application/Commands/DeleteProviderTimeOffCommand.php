<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class DeleteProviderTimeOffCommand
{
    public function __construct(
        public string $providerId,
        public string $timeOffId,
    ) {}
}
