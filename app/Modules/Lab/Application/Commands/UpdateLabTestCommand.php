<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class UpdateLabTestCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $testId,
        public array $attributes,
    ) {}
}
