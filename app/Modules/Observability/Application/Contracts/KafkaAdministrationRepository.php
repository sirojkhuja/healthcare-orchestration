<?php

namespace App\Modules\Observability\Application\Contracts;

use App\Modules\Observability\Application\Data\KafkaConsumerLagData;
use Carbon\CarbonImmutable;

interface KafkaAdministrationRepository
{
    /**
     * @return list<KafkaConsumerLagData>
     */
    public function listLag(): array;

    /**
     * @param  list<string>|null  $messageIds
     */
    public function clearReplayReceipts(string $consumerName, ?array $messageIds, ?CarbonImmutable $processedBefore, int $limit): int;
}
