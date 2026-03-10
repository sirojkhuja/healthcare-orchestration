<?php

namespace App\Modules\Scheduling\Application\Commands;

final readonly class OfferWaitlistSlotCommand
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $entryId,
        public array $attributes,
    ) {}
}
