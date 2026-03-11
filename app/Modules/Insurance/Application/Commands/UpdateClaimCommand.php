<?php

namespace App\Modules\Insurance\Application\Commands;

final readonly class UpdateClaimCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $claimId,
        public array $attributes,
    ) {}
}
