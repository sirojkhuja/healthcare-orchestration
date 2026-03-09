<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class CreateProviderGroupCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
