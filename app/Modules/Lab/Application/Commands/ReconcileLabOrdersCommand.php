<?php

namespace App\Modules\Lab\Application\Commands;

final readonly class ReconcileLabOrdersCommand
{
    /**
     * @param  list<string>  $orderIds
     */
    public function __construct(
        public string $labProviderKey,
        public array $orderIds = [],
        public int $limit = 50,
    ) {}
}
