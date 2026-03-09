<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class CreateProviderCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
