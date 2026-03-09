<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class DeleteSpecialtyCommand
{
    public function __construct(
        public string $specialtyId,
    ) {}
}
