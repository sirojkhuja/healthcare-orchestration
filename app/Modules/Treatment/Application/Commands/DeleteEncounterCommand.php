<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class DeleteEncounterCommand
{
    public function __construct(
        public string $encounterId,
    ) {}
}
