<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class UpdateProviderTimeOffCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $providerId,
        public string $timeOffId,
        public array $attributes,
    ) {}
}
