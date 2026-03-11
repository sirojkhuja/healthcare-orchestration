<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class UpdatePayerCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $payerId,
        public array $attributes,
    ) {}
}
