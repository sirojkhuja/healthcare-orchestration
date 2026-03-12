<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Commands\ReplayKafkaEventsCommand;
use App\Modules\Observability\Application\Data\KafkaReplayData;
use App\Modules\Observability\Application\Services\KafkaAdministrationService;

final class ReplayKafkaEventsCommandHandler
{
    public function __construct(private readonly KafkaAdministrationService $kafkaAdministrationService) {}

    public function handle(ReplayKafkaEventsCommand $command): KafkaReplayData
    {
        return $this->kafkaAdministrationService->replay(
            $command->consumerName,
            $command->eventIds,
            $command->processedBefore,
            $command->limit,
        );
    }
}
