<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class UpdateEncounterCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $encounterId,
        public array $attributes,
    ) {}
}
