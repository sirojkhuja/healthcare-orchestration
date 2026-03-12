<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Observability\Application\Contracts\KafkaAdministrationRepository;
use App\Modules\Observability\Application\Data\KafkaLagData;
use App\Modules\Observability\Application\Data\KafkaReplayData;
use Carbon\CarbonImmutable;

final class KafkaAdministrationService
{
    public function __construct(
        private readonly KafkaAdministrationRepository $kafkaAdministrationRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function lag(): KafkaLagData
    {
        $brokers = trim(config()->string('medflow.kafka.brokers', ''));

        return new KafkaLagData(
            brokers: $brokers === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $brokers)))),
            consumerGroup: config()->string('medflow.kafka.group_id'),
            consumers: $this->kafkaAdministrationRepository->listLag(),
            capturedAt: CarbonImmutable::now(),
        );
    }

    /**
     * @param  list<string>|null  $eventIds
     */
    public function replay(string $consumerName, ?array $eventIds, ?CarbonImmutable $processedBefore, int $limit): KafkaReplayData
    {
        $result = new KafkaReplayData(
            consumerName: $consumerName,
            eventIds: $eventIds,
            processedBefore: $processedBefore,
            limit: $limit,
            clearedCount: $this->kafkaAdministrationRepository->clearReplayReceipts(
                $consumerName,
                $eventIds,
                $processedBefore,
                $limit,
            ),
            performedAt: CarbonImmutable::now(),
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'admin.kafka_replay_enabled',
            objectType: 'kafka_consumer',
            objectId: $consumerName,
            after: $result->toArray(),
        ));

        return $result;
    }
}
