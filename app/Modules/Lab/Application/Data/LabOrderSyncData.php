<?php

namespace App\Modules\Lab\Application\Data;

final readonly class LabOrderSyncData
{
    /**
     * @param  list<LabResultData>  $results
     */
    public function __construct(
        public LabOrderData $order,
        public array $results,
        public int $resultCount,
    ) {}
}
