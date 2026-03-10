<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class DeleteLabTestCommand
{
    public function __construct(
        public string $testId,
    ) {}
}
