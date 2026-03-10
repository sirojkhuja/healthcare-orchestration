<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class CreateEncounterCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
