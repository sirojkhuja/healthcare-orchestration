<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class RemoveProcedureCommand
{
    public function __construct(
        public string $encounterId,
        public string $procedureId,
    ) {}
}
