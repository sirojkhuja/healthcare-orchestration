<?php

namespace App\Modules\Treatment\Application\Commands;

final readonly class RemoveTreatmentItemCommand
{
    public function __construct(
        public string $planId,
        public string $itemId,
    ) {}
}
