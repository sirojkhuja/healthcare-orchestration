<?php

namespace App\Modules\Provider\Application\Commands;

final readonly class UpdateSpecialtyCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $specialtyId,
        public array $attributes,
    ) {}
}
