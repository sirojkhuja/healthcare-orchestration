<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class UpdateProviderProfileCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $providerId,
        public array $attributes,
    ) {}
}
