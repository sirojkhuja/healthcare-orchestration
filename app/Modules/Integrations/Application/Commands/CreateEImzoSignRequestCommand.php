<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class CreateEImzoSignRequestCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
