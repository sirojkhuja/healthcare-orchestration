<?php

namespace App\Modules\Notifications\Application\Commands;

final readonly class SetTelegramProviderCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
