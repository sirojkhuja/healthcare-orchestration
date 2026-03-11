<?php

namespace App\Modules\Notifications\Application\Commands;

final readonly class SendNotificationCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
