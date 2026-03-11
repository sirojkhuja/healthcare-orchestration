<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class CreatePayerCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
