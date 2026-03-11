<?php

namespace App\Modules\Notifications\Application\Commands;

final readonly class SetSmsProvidersPriorityCommand
{
    /**
     * @param  list<array<string, mixed>>  $routes
     */
    public function __construct(
        public array $routes,
    ) {}
}
