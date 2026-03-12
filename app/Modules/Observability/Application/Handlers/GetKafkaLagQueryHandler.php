<?php

namespace App\Modules\Observability\Application\Handlers;

use App\Modules\Observability\Application\Data\KafkaLagData;
use App\Modules\Observability\Application\Queries\GetKafkaLagQuery;
use App\Modules\Observability\Application\Services\KafkaAdministrationService;

final class GetKafkaLagQueryHandler
{
    public function __construct(private readonly KafkaAdministrationService $kafkaAdministrationService) {}

    public function handle(GetKafkaLagQuery $query): KafkaLagData
    {
        return $this->kafkaAdministrationService->lag();
    }
}
