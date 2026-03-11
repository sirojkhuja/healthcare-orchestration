<?php

namespace App\Modules\Integrations\Application\Commands;

final readonly class SendTextUpSmsCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
