<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class CreateLabOrderCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
    ) {}
}
