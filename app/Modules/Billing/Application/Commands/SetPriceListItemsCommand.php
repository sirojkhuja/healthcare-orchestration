<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class SetPriceListItemsCommand
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public string $priceListId,
        public array $items,
    ) {}
}
